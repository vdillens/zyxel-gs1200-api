<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use App\Utils\ZyxelCrypt;
use Symfony\Component\Console\Input\Input;

// the name of the command is what users type after "php bin/console"
#[AsCommand(
    name: 'app:zyxel',
    description: 'Command to interact with Zyxel'
)]
class ZyxelCommand extends Command
{
    protected static $defaultName = 'app:zyxel';
    /**
     * Http Client
     *
     * @var HttpClientInterface
     */
    private $client;
    /**
     * Zyxel's password
     *
     * @var string
     */
    private $zyxelPassword;
    /**
     * Zyxel's IP
     *
     * @var string
     */
    private $zyxelIp;

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp('This command allows you to interact with zyxel')
            ->addArgument('cmd', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The command to launch')
            ->addOption('ip', null, InputOption::VALUE_OPTIONAL, 'Zyxel Ip, if set override the default parameters in env file')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Zyxel Password, if set override the default parameters in env file');
    }
    public function __construct(HttpClientInterface $client, $zyxelIp, $zyxelPassword)
    {
        parent::__construct();
        $this->client = $client;
        $this->zyxelIp = $zyxelIp;
        $this->zyxelPassword = $zyxelPassword;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseUrl = 'http://' . $this->zyxelIp;
        // If an Ip has been set, then override the value from configuration
        if ($input->getOption('ip')) {
            $baseUrl = 'http://' . $input->getOption('ip');
        }
        $zyxelPassword = ($input->getOption('password')) ? $input->getOption('password') : $this->zyxelPassword;

        return 0;
        // First, we need to login
        $output->writeln([
            'Zyxel Command',
            '============',
            '1/ Login',
        ]);

        $response = $this->client->request(
            'POST',
            $baseUrl . '/login.cgi',
            ['body' => ['password' => ZyxelCrypt::encryptPassword($zyxelPassword)]]
        );

        // Retrieve the secure token in cookies
        $cookies = $response->getHeaders()['set-cookie'][0];
        $cookieAuth = explode(";", $cookies)[0];

        // Second, We need to launch the command
        $output->writeln([
            '============',
            '2/ Activation du port',
        ]);
        /*
        1  --> 0 / 1
2  --> 0 / 2
3 --> 0 / 4
4 --> 0 / 8
5 --> 0 / 16
*/
        //port_settings P1 all_default P2 state_enabled flow_ctl_enabled poe_enabled speed_10
        //port_settings Px5 all_default

        // speed 0=Auto 2=10 4=100
        // Launch the command
        $response = $this->client->request(
            'POST',
            $baseUrl . '/port_state_set.cgi',
            [
                'body' => [
                    'g_port_state' => 31,
                    'g_port_flwcl' => 0,
                    'g_port_poe' => 15,
                    'g_port_speed0' => 0,
                    'g_port_speed1' => 0,
                    'g_port_speed2' => 0,
                    'g_port_speed3' => 0,
                    'g_port_speed4' => 0
                ],
                'headers' => [
                    'Referer' => $baseUrl . '/Port.html',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Set-Cookie' =>  $cookieAuth
                ]
            ]
        );

        // We wait 2 seconds in order to let the router do the action
        sleep(2);

        // We need to logout properly, otherwise the router will block the acces to the GUI
        $output->writeln([
            '============',
            '3/ Logout',
        ]);
        $response = $this->client->request(
            'GET',
            $baseUrl . '/logout.html',
            [
                'headers' => [
                    'Set-Cookie' =>  $cookieAuth
                ]
            ]
        );

        //Gestion du port 3 : désactivation 
        /*$response = $this->client->request(
            'POST',
            $baseUrl .'/port_state_set.cgi',
            [
                'body' => [
                    'g_port_state' => 31,
                    'g_port_flwcl' => 0,
                    'g_port_poe' => 11,
                    'g_port_speed0' => 0,
                    'g_port_speed1' => 0,
                    'g_port_speed2' => 0,
                    'g_port_speed3' => 0,
                    'g_port_speed4' => 0
                ],
                'headers' => [
                    'Referer' => $baseUrl .'/Port.html',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Set-Cookie' =>  $cookieAuth
                ]
            ]
        );
        sleep(5);

*/
        return Command::SUCCESS;

        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;

        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }
}
