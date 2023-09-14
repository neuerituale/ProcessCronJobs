<?php
/**
 * COPYRIGHT NOTICE
 * Copyright (c) 2021 Neue Rituale GbR
 * @author NR <code@neuerituale.com>
 */

namespace ProcessWire;

/**
 * @global ProcessCronJobs $processInstance
 * @global Config $config
 * @global WireCache $cache
 * @global WireDateTime $datetime
 */

?>

<?php if(!$processInstance->isEnabled()) : ?>
<div class="uk-card-body uk-text-danger uk-padding-small uk-text-center">
    <?= __('CronJobs are currently disabled ...'); ?>
</div>
<?php endif; ?>

<?php if(!count($processInstance->crons)) : ?>
    <div class="uk-card uk-card-primary uk-margin">
        <div>
            <div class="uk-card-body uk-text-lead uk-text-muted uk-padding-small uk-text-center">
                <?= __('No cronjobs registered.'); ?>
            </div>
        </div>
    </div>
<pre class="uk-text-small">
// Add this to your init.php
// register cronjobs via ProcessWire hook

wire()->addHookBefore('ProcessCronJobs::register', function(HookEvent $event){

    /** @var ProcessCronJobs $processCronJobs */
    $processCronJobs = $event->object;
    $processCronJobs->add(
        'MyFirstCronJob',
        function(CronJob $cron){
            // do something
        },
        ['lazyCron' => 'LazyCron::everyDay']
    );

});
</pre>
<?php else :

    // build table
    $table = wire()->modules->get('MarkupAdminDataTable');
    $table->headerRow([
        __('Name'),
        __('Type'),
        __('Timing'),
        __('Path'),
        __('Last run'),
        __('Last trigger'),
        __('Action')
    ]);
    $table->encodeEntities = false;
    $table->addClass('uk-table-striped');
    $table->addClass('uk-table-middle');
    $path = $processInstance->getPath(true);

    /** @var CronJob $cron */
    foreach ($processInstance->crons as $cron) {

        $row = [];
        $rowClasses = [];
        if($cron->disabled) $rowClasses[] = 'uk-text-muted';

        // Name
        $row[] = '&nbsp; ' . $cron->name;

        // Type
        $type = ($cron->lazyCron ?? 'OnDemand');
        if($cron->disabled) $type = "<s uk-tooltip='".__('This cron is currently disabled')."'>$type</s>";
        $typeInfo = $cron->getArray()['lazyCron'] !== $cron->lazyCron
            ? ' <a href="#" uk-icon="warning" uk-tooltip="'.__('Unfortunately, LazyCron setting is not allowed in crons with namespaces, as they block LazyCrons running in parallel.').'"></span>'
            : ''
        ;
        $row[] = $type . $typeInfo;

        // Timing
        $row[] = $cron->timingStr;

        // Path
        $row[] = $path . $cron->getPath();

        // Last run
        $row[] = $cron->trigger === CronJob::triggerNever
            ? __('Never')
            : $datetime->formatDate($cron->lastRun, $config->dateFormat)
            ;

        // trigger batch style
        $triggerInfo = !empty($cron->lastError)
            ? ' <a href="#" uk-icon="warning" uk-tooltip="title:'.$cron->lastError.'"></span>'
            : ''
        ;
        $triggerBatchClass = match($cron->trigger) {
            CronJob::triggerForce => 'uk-label-success',
            CronJob::triggerError => 'uk-label-danger',
            default => ''
        };
        $row[] = $cron->trigger !== CronJob::triggerNever
            ? '<span class="uk-badge uk-padding-small '.$triggerBatchClass.'">'.$cron->triggerStr. '</span>' . $triggerInfo
            : ''
            ;

        // Action
        $row[] =  '<a href="./run/'.$cron->name.'/" title="'
        .__('Execute this cronjob').'" class="uk-button uk-button-text">'
        .__('Run')
        .'</a>';

        // Add
        $table->row($row, ['class' => implode(' ', $rowClasses)]);
    }

    echo $table->render();

endif;

// last run
$lastRun = $cache->getFor(ProcessCronJobs::cacheNs, 'lastRun');
$lastRunStr = $lastRun > 0 ? $datetime->formatDate($lastRun, $config->dateFormat) : __('Never');
?>
<div class="uk-text-small uk-text-muted uk-margin-top">
    <?= sprintf(__('Last run: %s'), $lastRunStr); ?>
</div>
