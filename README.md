# ProcessCronJobs

## What it does
The module provides paths under which cronjobs can be registered. It lists all registered cronjobs and can execute individual ones manually. The last execution, the last status and, in the event of an error, the last error message are displayed.

## Features
- Clear overview of all registered CronJobs
- Easy to set timing (onInit or onReady) and delay (LazyCron)
- Individual path (endpoint) that executes the CronJobs. Configuration of a secret path segment and additional path segments (namespaces) for selected CronJobs.
- Display of the time of the last execution and any error messages

## Install

1. Copy the files for this module to /site/modules/ProcessCronJobs/
2. In admin: Modules > Refresh. Install ProcessCronJobs.
3. Go to Setup > CronJobs
4. Copy and Install example Module (`modules/ProcessCronJobs/example/ProcessCronJobsRegistration.module.example`) to register your CronJobs in the `__constructor()` method.
You can also use any other `__constructor()` method. It makes sense to register the CronJobs as early as possible so that they can also be executed on onInit.
5. Set up the real cron that calls the ProcessCronJobs provided endpoint.
	- Type `crontab -e` in your unix console
	- Add this line and save the file: `* * * * * curl --silent "https://example.com/cron/"` [Find out more about setting up CronJobs (Wikipedia).](https://en.wikipedia.org/wiki/Cron)

## Install via composer
1. Execute the following command in your website root directory.
   ```bash
   composer require nr/processcronjobs
   ```

## Register a CronJob

### Simple
A simple example for registering a CronJob.
```php
wire()->addHookBefore('ProcessCronJobs::register', function(HookEvent $event){
	/** @var ProcessCronJobs $processCronJobs */
	$processCronJobs = $event->object;
	$processCronJobs->add(
		'SuperSimpleCronJobOnDemand',
		function(CronJob $cron){ echo "Hello Cron"; },
	);
});
```

### Delayed
This CronJob should run once a day at init.

```php
wire()->addHookBefore('ProcessCronJobs::register', function(HookEvent $event){
	/** @var ProcessCronJobs $processCronJobs */
	$processCronJobs = $event->object;
	$processCronJobs->add(
		'MyFirstCronJobEveryDay',
		function(CronJob $cron){ echo "What a beautiful day"; },
		[
			'lazyCron' => 'LazyCron::everyDay',
			'timing' => CronJob::timingInit,
		]
	);
});
```

### Long Running
This CronJob runs for a very long time and is called directly by a "real" CronJob so as not to block other CronJobs.
The endpoint for this CronJob is https://example.com/cron/longrunning/ or https://example.com/cron/###your_secret###/longrunning/.

CronJobs that have a namespace (own path segment) cannot be delayed with LazyCron,
because LazyCron can only be started by a single request.
LazyCron creates a lock file and thus blocks the execution of parallel calls.

```php
wire()->addHookBefore('ProcessCronJobs::register', function(HookEvent $event){
	/** @var ProcessCronJobs $processCronJobs */
	$processCronJobs = $event->object;
	$processCronJobs->add(
		'SuperLongRunningSpecialCronJob',
		function(CronJob $cron){ echo "What a beautiful day"; },
		[
			'timing' => CronJob::timingInit,
			'ns' => 'longrunning'
		]
	);
});
```

## Process View
![ProcessView](https://user-images.githubusercontent.com/11630948/268062278-458b8060-a81d-4149-822d-6e3453a043a1.png)

## Configuration
`Modules` > `Configure` > `ProcessCronJobs`

You will find the following configuration options in the module settings:
- The trigger path, i.e. the path that triggers the CronJob processing, can be adjusted here (default: cron/).
- A secret path segment can be created that must be appended to the trigger path so that processing is started.
- Automatic processing can generally be stopped (status).
- The cache of ProcessCronJobs can be emptied. Things like the time of the last call, the status of the last call and possibly an error message are stored in the cache.

![Configuration](https://user-images.githubusercontent.com/11630948/268075104-79d78c14-ea8a-4735-80ee-1c32ecddd73d.png)

## The CronJob object

| Option      | Type         | Default                   | Description                                                                                                                                                                                                                                                                                                                                                                                  |
|-------------|--------------|---------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `name`      | String       |                           | Unique name in PascalCase e.g. `MyFirstCronJob`.                                                                                                                                                                                                                                                                                                                                             |
| `callback`  | Callable     | function(CronJob $cron){} | Function to be executed                                                                                                                                                                                                                                                                                                                                                                      |
| `lazyCron`  | null, String | `null`                    | If empty, the CronJob is executed without delay as soon as the path is called.                                                                                                                                                                                                                                                                                                               |
| `ns`        | null, String | `null`                    | If empty, the CronJob is called via the default path.                                                                                                                                                                                                                                                                                                                                        |
| `timing`    | Integer      | `CronJob::timingReady`    | The CronJon can be called either at onInit (1) or onReady (2). OnInit is earlier and therefore faster, but not all functions of ProcessWire are available here, e.g. page and language.                                                                                                                                                                                                      |
| `disabled`  | Boolean      | `false`                   | This can be used to deactivate the cronjob, e.g. `disabled = $config->debug`.                                                                                                                                                                                                                                                                                                                |
| `trigger`   | Integer      | `CronJob::triggerNever`   | Displays the last trigger for execution. Possible values are: <br />1 (Never): CronJob has never been executed <br />2 (Auto): CronJob was last executed directly via the "real" Cron (onDemand). <br />4 (Lazy): CronJob was called up with a time delay via the LazyCron. <br />8 (Force): The CronJob was started manually <br />16 (Error): The last call ended with an error (see log). |
| `lastRun`   | Integer      | 0                         | Contains the last execution time as a Unix timestamp. Stored and retrieved in the ProcessWire cache.                                                                                                                                                                                                                                                                                         |
| `lastError` | String       |                           | Possible error message from the last call                                                                                                                                                                                                                                                                                                                                                    |