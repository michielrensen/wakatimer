<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Laravel\Lumen\Application;
use SEOshop\Service\Contracts\JiraServiceInterface;
use SEOshop\Service\Contracts\TimerServiceInterface;

class TimerDailyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timer:daily
                            {date? : The date of the entries to be parsed}
                            {project? : The project to be parsed}
                            {--e|export : Whether the results should be exported to Toggl}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get daily timer stats per project';

    /**
     * @var TimerServiceInterface
     */
    protected $timerService;

    /**
     * @var JiraServiceInterface
     */
    protected $jiraService;

    /**
     * Create a new command instance.

     * @return void
     */
    public function __construct(Application $app, TimerServiceInterface $timerService, JiraServiceInterface $jiraService)
    {
        parent::__construct();

        $this->timerService = $timerService;
        $this->jiraService = $jiraService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $results = $this->timerService->handle($this->argument('date'), $this->argument('project'));

            $export = $this->option('export');

            if ($export === true)
            {
                $this->timerService->exportResults($results);
            }

            // Display results
            $this->displayResults($results);
        }
        catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }

    protected function displayResults($results)
    {
        $headers = ['date', 'project', 'author', 'description', 'tickets', 'time'];

        foreach($results as $result)
        {
            $rows = [];

            foreach($result['commits'] as $commit)
            {
                // Try to guess the ticket from the branch name
                $tickets = $this->jiraService->parseTicket(array_get($commit, 'ref'));

                if (empty($tickets))
                {
                    // Find tickets in the commit message
                    $tickets = $this->jiraService->parseTicket(array_get($commit, 'message'));
                }

                $rows[] = [
                    'date' => Carbon::parse($commit['author_date'])->format('d-m-Y H:i:s'),
                    'project' => $result['project'],
                    'author' => $commit['author_name'],
                    'description' => '- ' . mb_strimwidth($commit['message'], 0, 150, '...'),
                    'ticket' => implode(', ', $tickets),
                    'time (s)' => $commit['total_seconds'],
                ];
            }

            $this->comment('Project: ' . $result['project']);
            $this->comment('Date of report: ' . Carbon::parse($commit['author_date'])->format('d-m-Y'));
            $this->comment('Amount of commits: ' . count($rows));
            $this->line('');
            $this->table($headers, $rows);
            $this->line('');
        }
    }
}