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
    public function reset(): static {

        $this->data = [];
        $this->setArray([
            'name' => '',
            'callback' => function() {},
            'lazyCron' => null,
            'ns' => null,
            'timing' => self::timingReady,
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
    public function set($key, $value) {

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

        else if ($key === 'callback' && !is_callable($value)) {
            $value = function() {};
        }

        else if($key === 'trigger') {
            $value = (int) $value;
        }

        else if($key === 'ns') {
            $value = $sanitizer->pagePathName($value);
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

        else if($key === 'lazyCron' && !empty($this->ns)) {
            return null;
        }

        return parent::get($key);
    }

    /**
     * Update last run to current time
     * @return void
     */
    public function updateLastRun() {
        $this->lastRun = time();
    }

    /**
     * get Path
     * @return string
     */
    public function getPath() {
        return $this->ns ? (trim($this->ns, '/') . '/') : '';
    }

    /**
     * Run cron job
     * @param $force
     * @return bool|void
     * @throws WireException
     */
    public function run($force = false) {
        if(!is_callable($this->callback)) return false;

        // via lazy cron
        if($this->lazyCron && !$force) {

            if($this->disabled) return false;
            $cron = $this;

            wire()->addHook($this->lazyCron, function() use($cron) {
                try {
                    call_user_func_array($cron->callback, [$cron]);
                    $cron->trigger = self::triggerLazy;
                    $cron->set('lastError', '')->updateLastRun();
                    return true;
                }
                catch(Exception $exception) {
                    wire()->log(
                        sprintf($cron->_('CronError in "%1$s": %2$s'), $cron->name, $exception->getMessage()),
                        ['name' => self::errorLog]
                    );

                    $cron
                        ->set('trigger', self::triggerError)
                        ->set('lastError', $exception->getMessage())
                        ->updateLastRun();
                    return false;
                }
            });
        }

        // every time or force
        else {
            try {
                call_user_func_array($this->callback, [$this]);
                $this->trigger = $force ? self::triggerForce : self::triggerAuto;
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
        }
    }
}