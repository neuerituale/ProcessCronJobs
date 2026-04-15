<?php
/**
 * COPYRIGHT NOTICE
 * Copyright (c) 2023 Neue Rituale GbR
 * @author NR <code@neuerituale.com>
 */

namespace ProcessWire;

class ProcessCronJobsConfig extends ModuleConfig
{
    protected $cacheEntriesCount = 0;
    public function __construct() {
        parent::__construct();
        $this->cacheEntriesCount = count(wire()->cache->getFor(CronJob::cronjobCacheNs, '*'));
    }

    /**
	 * @return array
     */
	public function getDefaults(): array {
		return [
			'path' => 'cron/',
			'secret' => '',
            'enabled' => true,
            'clearCaches' => false
		];
	}

	/**
	 * @return InputfieldWrapper
     */
	public function getInputfields() {

		$inputfields = parent::getInputfields();

		$config = wire()->config;
		$data = wire()->modules->getConfig('ProcessCronJobs');
		$path = trim($data['path'] ?? 'cron', '/') . '/';
		$secret = $data['secret'] ?? '';
		$url = rtrim($config->httpHost, '/') . $config->urls->root . $path;
		if(!empty($secret)) $url .= trim($secret) . '/';

		$inputfields->add([
			'type' => 'InputfieldMarkup',
			'name' => '_cronCommand',
			'label' => $this->_('Cron command'),
			'description' => $this->_('Add this line to your cron configuration.'),
			'collapsed' => Inputfield::collapsedNever,
			'themeOffset' => 'm',
			'themeColor' => 'secondary',
			'value' => '<pre><code>* * * * * curl --silent ' . $url . ' &>/dev/null</code></pre>',
		]);

		$inputfields->add([
			'type' => 'InputfieldText',
			'name' => 'path',
			'label' => $this->_('Trigger path'),
            'description' => $this->_('The path to be called by the cronjob.'),
            'notes' => $this->_('Set to `cron/` for a cron url like this: `https://example.come/cron/`'),
            'required' => true,
            'columnWidth' => 50
		]);

        $inputfields->add([
            'type' => 'InputfieldText',
            'name' => 'secret',
            'label' => $this->_('Secret segment'),
            'description' => $this->_('You can add a secret path segment to better protect your Cron calls.'),
            'notes' => $this->_('Please use only path compatible characters like this: `[a-zA-Z0-9\-]`'),
			'pattern' => '[a-zA-Z0-9\-]+',
            'required' => false,
            'columnWidth' => 50
        ]);

        /** @var InputfieldToggle */
        $inputfields->add([
            'type' => 'Toggle',
            'name' => 'enabled',
            'labelType' => InputfieldToggle::labelTypeCustom,
            'yesLabel' => $this->_('Enabled'),
            'noLabel' => $this->_('Disabled'),
            'label' => __('Status'),
            'notes' => $this->_('You can also control the status via the hookable method `ProcessCronJobs::isEnabled`. If you have hooks that are called onInit, this should happen in a `__construct()` method of a module.'),
            'columnWidth' => 50,
        ]);

        /** @var InputfieldCheckbox */
        $description = $this->cacheEntriesCount ? sprintf($this->_('%s cache items found.'), $this->cacheEntriesCount) : $this->_('The cache is empty');
        $inputfields->add([
            'type' => 'Checkbox',
            'name' => 'clearCaches',
            'label' => __('Caches'),
            'checkboxLabel' => __('Clear all caches'),
            'description' => $description,
            'columnWidth' => 50,
        ]);

		return $inputfields;
	}
}