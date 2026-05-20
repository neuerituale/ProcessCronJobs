<?php
/**
 * COPYRIGHT NOTICE
 * Copyright (c) 2023 Neue Rituale GbR
 * @author NR <code@neuerituale.com>
 */

namespace ProcessWire;

use Exception;

class CronJob extends WireData {

    const errorLog = 'cronjobs-errors';
    const cronjobCacheNs = 'CronJob';

    const intervals = [
        'every30Seconds' => 30,
        'everyMinute'    => 60,
        'every2Minutes'  => 120,
        'every3Minutes'  => 180,
        'every4Minutes'  => 240,
        'every5Minutes'  => 300,
        'every10Minutes' => 600,
        'every15Minutes' => 900,
        'every30Minutes' => 1800,
        'every45Minutes' => 2700,
        'everyHour'      => 3600,
        'every2Hours'    => 7200,
        'every4Hours'    => 14400,
        'every6Hours'    => 21600,
        'every12Hours'   => 43200,
        'everyDay'       => 86400,
        'every2Days'     => 172800,
        'every4Days'     => 345600,
        'everyWeek'      => 604800,
        'every2Weeks'    => 1209600,
        'every4Weeks'    => 2419200,
    ];

    const triggerNever = 1;
    const triggerAuto = 2;
    const triggerLazy = 4;
    const triggerForce = 8;
    const triggerError = 16;

    const timingInit = 1;
    const timingReady = 2;

    public function __construct() {
        parent::__construct();
        $this->reset();
    }

    /**
     * Reset Cron object
     * @return $this
     */
    public function reset(): CronJob {

        $this->data = [];
        $this->setArray([
            'name' => '',
            'callback' => function() {},
            'lazyCron' => null,
            'ns' => null,
            'timing' => self::timingReady,
	        'user' => null,
	        'notes' => [],
            'lastRun' => 0,
            'disabled' => false,
            'trigger' => self::triggerNever,
            'lastError' => ''
        ]);

        return $this;
    }

    /**
     * Set
     * @param $key
     * @param $value
     * @return CronJob
     */
    public function set($key, $value): CronJob {

        $sanitizer = wire()->sanitizer;

        if($key === 'name' && !empty($value)) {
            $value = $sanitizer->pascalCase($value);

            // load last run from cache
            $data = wire()->cache->getFor(
                self::cronjobCacheNs,
                $sanitizer->pascalCase($value),
                WireCache::expireNever
            );

            if(is_array($data) && count($data) >= 2) {
                $this->data['lastRun'] = (int) $data[0]; // set quietly
                $this->data['trigger'] = (int) $data[1]; // set quietly
                $this->data['lastError'] = $data[2] ?? ''; // set quietly
            }
        }

        else if($key === 'trigger') {
            $value = (int) $value;
        }

        else if($key === 'ns') {
            $value = $sanitizer->pagePathName($value);
        }

        else if($key === 'user') {
            if(empty($value)) {
                $value = null;
            } else {
                $search = $value;
                if(!$value instanceof User) $value = wire()->users->get($value);
                if(!$value->id) {
                    $this->disabled = true;
                    $this->addNote(sprintf($this->_('User "%s" not found'), $search));
                    $value = null;
                }
            }
        }

        else if($key === 'lastRun') {
            // persist value
            $value = (int) $value;
            if($value > 0) {
                wire()->cache->saveFor(
                    self::cronjobCacheNs,
                    $sanitizer->pascalCase($this->name),
                    [$value, $this->trigger, $this->lastError],
                    WireCache::expireNever
                );
            }
        }

        return parent::set($key, $value);
    }

    /**
     * @param $key
     * @return mixed|string|null
     */
    public function get($key) {
        if($key === 'triggerStr') {
            return match($this->trigger) {
                self::triggerAuto => $this->_('Auto'),
                self::triggerLazy => $this->_('Lazy'),
                self::triggerForce => $this->_('Manual'),
                self::triggerError => $this->_('Error'),
                default => __('Unknown')
            };
        }

        else if($key === 'timingStr') {
            return match($this->timing) {
                self::timingInit => $this->_('onInit'),
                self::timingReady => $this->_('onReady'),
                default => __('Unknown')
            };
        }

		else if($key === 'typeStr') {
			$name = preg_replace('/^LazyCron::/i', '', (string) $this->lazyCron);
			return $name ? ucfirst($name) . ' (Lazy)' : 'OnDemand';
		}

		else if($key === 'callback') {
			$callback = $this->data['callback'];
			return is_callable($callback)
				? $callback
				: function () { throw new \Exception('Callback is not callable'); };

		}

        return parent::get($key);
    }

	/**
	 * Resolve the configured lazyCron string to seconds
	 * Accepts both `LazyCron::everyHour` and bare `everyHour` notation.
	 * @return int|null Seconds, or null if no/unknown lazyCron set
	 */
	public function getInterval(): ?int {
		if(empty($this->lazyCron)) return null;
		$name = preg_replace('/^LazyCron::/i', '', (string) $this->lazyCron);
		return self::intervals[$name] ?? null;
	}

	/**
	 * Is the cron due to run based on lastRun and its interval?
	 * @return bool
	 */
	public function isDue(): bool {
		$interval = $this->getInterval();
		if($interval === null) return false;
		return (time() - (int) $this->lastRun) >= $interval;
	}

	/**
	 * Add a note to notes
	 *
	 * @param $note
	 * @return $this
	 */
	public function addNote($note): CronJob {
		$notes = $this->notes;
		$notes[] = $note;
		$this->set('notes', $notes);
		return $this;
	}

    /**
     * Update last run to current time
     * @return void
     */
    public function updateLastRun(): void {
        $this->lastRun = time();
    }

    /**
     * get Path
     * @return string
     */
    public function getPath(): string {
        return $this->ns ? (trim($this->ns, '/') . '/') : '';
    }

	/**
	 * Execute the cron job callback
	 * @param int $successTrigger
	 * @return bool
	 */
    private function execute(int $successTrigger): bool {

        $previousUser = null;
        if($this->user instanceof User) {
            $previousUser = wire()->user;
            wire()->users->setCurrentUser($this->user);
        }

        try {
            call_user_func_array($this->callback, [$this]);
            $this->trigger = $successTrigger;
            $this->set('lastError', '')->updateLastRun();
            return true;
        }
        catch(Exception $exception) {
            wire()->log(
                sprintf($this->_('CronError in "%1$s": %2$s'), $this->name, $exception->getMessage()),
                ['name' => self::errorLog]
            );
            $this
                ->set('trigger', self::triggerError)
                ->set('lastError', $exception->getMessage())
                ->updateLastRun();
            return false;
        }
        finally {
            if($previousUser) wire()->users->setCurrentUser($previousUser);
        }
    }

	/**
	 * Run cron job
	 * @param bool $force
	 * @return bool|void
	 * @throws WireException
	 */
    public function run(bool $force = false) {

		// skip invalid & disabled
        if(
			!is_callable($this->callback) ||
			($this->disabled && !$force)
        ) return false;

        // via lazy cron: own due-check, independent from ProcessWire's LazyCron module
        if($this->lazyCron && !$force) {
            if($this->getInterval() === null) return false;
            if(!$this->isDue()) return false;
            return $this->execute(self::triggerLazy);
        }

        // every time or force
        return $this->execute($force ? self::triggerForce : self::triggerAuto);
    }
}