<?php

namespace Upaidpckg\Config\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Upaidpckg\Config\Services\CommandService as Service;

/**
 * GetConfigCommand class
 *
 * This class gets the requested file from remote (or local) server,
 * validates data and updates this file in application.
 *
 * Example usage:
 * To update .env file just call in console:
 *
 * php artisan config:get http://your_remote_server_url/.env
 *
 * @package  Upaidpckg
 * @author   Michał Zwierzyński <michal.zwierzynski@upaid.pl>
 */
class GetConfigCommand extends Command
{

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update application config.';

    /**
     * Command service
     *
     * @var Service
     */
    public $cfgService;


    /**
     * GetConfigCommand constructor.
     *
     * @param Service $service
     */
    public function __construct(Service $service)
    {
        $this->cfgService = $service;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this
            ->setName('config:get {param}')
            ->setDescription('Get config for application from remote server.')
            ->setAliases(['getConfig'])
            ->setDefinition(
                [new InputArgument('param', InputArgument::OPTIONAL),]
            );
    }

    /**
     * Executes the console command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return mixed
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $answer = null;
        $param = $input->getArgument('param');

        if (empty($param)) {
            $answer = $this->ask('Define configuration server url');
        }

        $url = $answer ? $answer : $param;

        $exploded = explode('/', $url);
        $urlFilename = end($exploded);
        $urlAppName = prev($exploded);

        $output->writeln('Trying to get ' . $urlFilename . ' from: ' . $url);
        $this->getConfig($url, $output, $urlFilename, $urlAppName);

    }

    /**
     * Get configuration
     *
     * @param                 $url
     * @param OutputInterface $output
     * @param                 $fileName
     * @param                 $appName
     *
     * @return mixed
     */
    private function getConfig($url, OutputInterface $output, $fileName, $appName)
    {
        $ext = pathinfo($url, PATHINFO_EXTENSION);

        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            $data = curl_exec($curl);
            if (!curl_errno($curl) && !$this->cfgService->validate($data, $ext)) {
                $output->writeln('Validation of remote ' . $fileName . ' file failed!');
            } else {
                if (!curl_errno($curl)) {
                    $output->writeln(
                        'Configuration downloaded and validated.'
                    );
                    $output->writeln('Creating backup...');
                    $backupResponse = $this->createBackup($fileName, $appName);
                    switch ($backupResponse['code']) {
                        case 0:
                            $output->writeln(
                                'File ' . $fileName . ' already backuped!'
                            );
                            break;
                        case 1:
                            $output->writeln(
                                'Backup created successfully!'
                            );
                            break;
                        case 2:
                            $output->writeln('Failed to create backup!');
                            break;
                    }

                    $output->writeln('Updating ' . $fileName . ' file...');

                    $message = $this->updateFile($fileName, $data, $appName)
                        ? 'File update success!'
                        : 'Failed to update ' . $fileName . ' file!';

                    $output->writeln($message);
                } else {
                    $output->writeln(
                        'Curl error: ' . curl_errno($curl)
                        . '. Check http://php.net/manual/en/function.curl-errno.php for more information.'
                    );
                }
            }
            curl_close($curl);
        } catch (\Exception $ex) {
            $output->writeln('Failed to get content of ' . $fileName . ' file from: ' . $url);
            $output->writeln($ex->getMessage());
        }
    }

    /**
     * Updates downloaded configuration data to requested file
     *
     * @param $fileName
     * @param $data
     * @param $appName
     *
     * @return bool
     */
    private function updateFile($fileName, $data, $appName)
    {
        $filePath = $this->cfgService->getFilePath($fileName, $appName);
        return  file_put_contents($filePath, $data) !== false;
    }

    /**
     * Creates backup of file
     *
     * @param $fileName
     * @param $appName
     *
     * @return array :
     * 'code' => 0 | File already backuped. Original file and backuped fila are the same. No need to backup it again.
     * 'code' => 1 | Backup created successfully!
     * 'code' => 2 | Failed to create backup!
     */
    private function createBackup($fileName, $appName)
    {
        $return = ['code' => 0];

        $filePath = $this->cfgService->getFilePath($fileName, $appName);
        if (!file_exists($filePath)) {
            return ['code' => 2];
        }

        $isEqual = is_dir('config_backup') ? md5(file_get_contents($filePath)) !== md5(file_get_contents('config_backup/' . $fileName . '.bc')) : false;

        if (!file_exists('config_backup/' . $fileName . '.bc') || $isEqual) {
            $return = ($this->cfgService->makeBcDir() && copy($filePath, 'config_backup/' . $fileName . '.bc')) ?
                ['code' => 1] :
                ['code' => 2];
        }

        chmod('config_backup/' . $fileName . '.bc', 0755);
        return $return;
    }
}
