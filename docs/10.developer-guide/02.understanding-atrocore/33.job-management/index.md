
---
title: Job Management
---

AtroCore includes a powerful background job system that allows you to execute long-running or resource-intensive tasks
asynchronously.
This system helps improve application responsiveness by offloading heavy operations to be processed in the background.

Jobs in AtroCore follow a lifecycle (Pending → Running → Success/Failed) and are managed by a dedicated job processing
manager that runs via cron.

## Creating a Job

To implement a background job, follow these steps:

### 1. Create a Job Class

Create a new PHP class in the `Jobs` directory of your module. The class should implement the `\Atro\Jobs\JobInterface`
and may extend the `\Atro\Jobs\AbstractJob` class (recommended for access to common services).

**Example: `Jobs/ExampleJob.php`**

```php
<?php

declare(strict_types=1);

namespace YourModule\Jobs;

use Atro\Jobs\AbstractJob;
use Atro\Jobs\JobInterface;
use Atro\Entities\Job;
use Espo\Core\Exceptions\Error;

class ExampleJob extends AbstractJob implements JobInterface
{
    /**
     * Execute the job logic
     *
     * @param Job $job The job entity with metadata and payload
     *
     * @throws Error When something goes wrong
     */
    public function run(Job $job): void
    {
        // Access job payload data
        $payload = $job->getPayload();
        $data1 = $payload['data1'] ?? null;

        // Access services via AbstractJob parent methods
        $entityManager = $this->getEntityManager();
        $metadata = $this->getMetadata();

        // Implement your job logic here


        // Log information for debugging
        $this->getContainer()->get('log')->debug('ExampleJob: Processing completed');
    }
}
```

The `AbstractJob` parent class provides access to commonly used container services:

- Entity Manager
- Metadata
- Event Manager
- Service Factory
- Config
- Language
- Memory Storage
- Twig engine
- Container (for other container services)

### 2. Register the Job Type

Register your job in the module's metadata by creating/updating the file `Resources/metadata/app/jobTypes.json`:

```json
{
    "ExampleJob": {
        "scheduledJob": true,
        "handler": "\\YourModule\\Jobs\\ExampleJob"
    }
}
```

**Properties:**

- `scheduledJob`: When `true`, this job type becomes available for scheduling in the Admin panel
- `handler`: The fully qualified namespace of your job class

!! When registering new job types, choose unique names carefully.
!! If you register a job with the same name as an existing job type (either from core or another module), your implementation will override the original one.
!! This can lead to unexpected behavior, broken functionality, or system instability.

Following these naming conventions helps prevent conflicts and makes your code more safe.

## Dispatching Jobs

To enqueue a job for background processing:

```php
/** @var \Espo\Core\ORM\EntityManager $entityManager */
$entityManager = $container->get('entityManager');

// Create a job entity
$jobEntity = $entityManager->getEntity('Job');
$jobEntity->set([
    'name'     => "Process data for product X", // Human-readable description
    'type'     => 'ExampleJob',                 // Job type name from metadata
    'priority' => 100,                          // Optional, Higher number = higher priority
    'payload'  => [                             // Data passed into the job
        "productId" => "123abc",
        "userId" => "user-456",
        "options" => [
            "processImages" => true,
            "updateRelated" => true
        ]
    ]
]);

// Save to queue the job
$entityManager->saveEntity($jobEntity);
```

Once saved, the job is added to the queue with `Pending` status. The job manager will pick it up during the next cron
run.

## Job Lifecycle and States

Jobs in AtroCore can have the following states:

| State    | Description                                                        |
|----------|--------------------------------------------------------------------|
| Pending  | Newly created job waiting to be processed                          |
| Running  | Currently being executed by the job manager                        |
| Success  | Successfully completed                                             |
| Failed   | Execution failed (error details are stored in the `message` field) |
| Canceled | Manually canceled before completion                                |

The job manager automatically handles state transitions. When a job fails, detailed error information is stored in the
job's `message` field for troubleshooting.

## Canceling Jobs

You can cancel a job at any time by simply updating its status to `Canceled`. This is useful when you need to stop a
pending job before it starts processing or to signal a running job that it should stop (if the job implementation checks
for cancellation).

**Example: Canceling a Job**

```php
/** @var \Espo\Core\ORM\EntityManager $entityManager */
$entityManager = $container->get('entityManager');

// Get the job you want to cancel
$jobId = 'some-id';
$job = $entityManager->getEntity('Job', $jobId);

// Update status to Canceled
$job->set('status', 'Canceled');
$entityManager->saveEntity($job);
```

## Accessing Job Results and Error Information

To check the status or error details of a job:

```php
$jobId = '12345'; // ID of the previously created job
$job = $entityManager->getEntity('Job', $jobId);

$status = $job->get('status');    // 'Pending', 'Running', 'Success', 'Failed', 'Canceled'

// Process ID for running jobs
if ($job->get('status') === 'Running') {
    $pid = $job->get('pid');      // Linux process ID of the running job
    echo "Job is running as process ID: " . $pid;
}

// For failed jobs, check the error message
if ($job->get('status') === 'Failed') {
    $errorMessage = $job->get('message'); // Contains detailed error information
    echo "Job failed: " . $errorMessage;
}
```

Key job information fields:

- `status`: Current state of the job
- `message`: Error details when a job fails
- `pid`: Linux process ID of the job when it's in the "Running" state

The `pid` field is particularly useful for system administrators when troubleshooting or monitoring job execution. It
allows correlating AtroCore jobs with actual system processes, which can help identify resource usage issues or
terminate stuck processes if necessary.

When a job encounters an exception or error during execution, the job manager automatically:

1. Sets the job status to `Failed`
2. Captures the exception message
3. Stores this information in the job's `message` field

This makes it easy to diagnose and fix issues with failed jobs by examining the error message directly in the job
record.

## Concurrent Job Processing

AtroCore's job manager supports parallel processing of multiple jobs simultaneously to maximize throughput and efficiency.
This is especially beneficial for systems with many jobs or when processing needs to scale.

### Worker Configuration

The job processing system can run multiple concurrent worker processes, each handling one job at a time. The number of concurrent workers is configurable via the `maxConcurrentWorkers` parameter in your configuration:

```php
// In data/config.php
return [
    // other config
    'maxConcurrentWorkers' => 10, // Set to run 10 job workers in parallel
    // other config
];
```

**Worker Configuration Guidelines:**
- **Default value**: 6 concurrent workers
- **Minimum value**: 4 workers (enforced by the system)
- **Maximum value**: 50 workers (enforced by the system)
- **Recommended setting**: Adjust based on your server resources and typical job workload

### Performance Considerations

When setting the number of concurrent workers, consider:

- **CPU resources**: Each worker consumes CPU time. Too many workers on a system with limited CPU can degrade performance.
- **Memory usage**: More workers require more RAM. Monitor memory usage to prevent swapping.
- **Job characteristics**: CPU-bound jobs benefit less from high concurrency than I/O-bound jobs.
- **Server load**: On shared hosting or multi-tenant environments, be mindful of overall system impact.

For most production environments, a value between 8-12 workers provides good throughput without excessive resource consumption.

## Job Processing Manager

Pending jobs in the queue are processed based on priority via the job manager daemon, which is a background process that is always active. The job manager daemon is started via cron. Ensure that the cron task is
properly configured on your server.

```
* * * * * www-data php /path_to_atrocore/console.php cron
```

To verify the cron configuration (if you have server access):

```bash
sudo -u www-data crontab -e
```

This should show the AtroCore cron command.

## Manual Job Execution

For testing or troubleshooting, you can manually execute a job regardless of its current state:

```bash
php console.php job <jobId> --run
```
or in debug mode
```bash
php console.php job debug_<jobId> --run
```
> In debug mode, the job is executed but the status is not updated. This is useful for debugging failed jobs.

## Scheduled Jobs

Jobs marked with `"scheduledJob": true` in metadata can be scheduled to run periodically via the Admin panel:

1. Navigate to Administration → Scheduled Jobs
2. Create a new scheduled job and select your job type
3. Configure the execution schedule (cron syntax)

For more information about configuring scheduled jobs, see
the [Scheduled Jobs documentation](../../../01.atrocore/03.administration/05.system-jobs/01.scheduled-jobs).

## Best Practices

1. **Job Granularity**: Design jobs to be as granular as possible, focusing on specific tasks rather than complex
   workflows
2. **Error Handling**: Implement comprehensive error handling in your job's `run()` method
3. **Resource Consideration**: Be mindful of resource usage, especially for jobs that process large datasets
4. **Transaction Management**: Use database transactions where appropriate to ensure data consistency
5. **Logging**: Add debug logs to help with troubleshooting
6. **Timeouts**: Be aware of potential timeouts for very long-running jobs
7. **Payload Size**: Keep job payloads reasonably small; store large data in temporary storage if needed
8. **Error Inspection**: Always check the `message` field of failed jobs to understand what went wrong
9. **Process Monitoring**: Use the `pid` field to monitor system resources for long-running jobs

## Troubleshooting

- **Job never executes**: Verify that the cron task is running correctly
- **Job fails immediately**: Check the job's `message` field for detailed error information
- **Job gets stuck in "Running"**: Check the process with the ID from the `pid` field to see if it's still active
- **Slow job execution**: Consider optimization or breaking into smaller jobs
- **High memory usage**: Use the `pid` to monitor resource consumption and implement batch processing
- **Recurring failures**: Examine error patterns in the `message` field of failed jobs to identify systemic issues
- **Emergency termination**: For truly stuck jobs, you can use the Linux `kill` command with the job's `pid` (use with
  caution)

---

With the job system, you can build powerful background processing capabilities while maintaining responsive user
interfaces for your AtroCore applications.








