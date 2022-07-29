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
use App\Utils\ZyxelParser\ZyxelCommandParser;
use App\Utils\ZyxelParser\ZyxelCommandParserException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\Input;
use Symfony\Contracts\HttpClient\ResponseInterface;

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
    /**
     * Zyxel's device
     *
     * @var string
     */
    private $zyxelDevice;

    protected function configure(): void
    {
        $this
            // the command help shown when running the command with the "--help" option
            ->setHelp('This command allows you to interact with zyxel')
            ->addArgument('cmd', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The command to launch')
            ->addOption('ip', null, InputOption::VALUE_OPTIONAL, 'Zyxel Ip, if set override the default parameters in env file')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Zyxel Password, if set override the default parameters in env file')
            ->addOption('device', null, InputOption::VALUE_OPTIONAL, 'Zyxel device ( 5HP ==> GS1200-5HPv2, 8HP ==> GS1200-8HPv2 ), if set override the default parameters in env file');
    }
    public function __construct(HttpClientInterface $client, $zyxelIp, $zyxelPassword, $zyxelDevice)
    {
        parent::__construct();
        $this->client = $client;
        $this->zyxelIp = $zyxelIp;
        $this->zyxelPassword = $zyxelPassword;
        $this->zyxelDevice = $zyxelDevice;
    }
    private function checkLoginZyxel(ResponseInterface $response): bool
    {
        // Check StatusCode
        if ($response->getStatusCode() != 200) {
            if ($response->getStatusCode() == 404) {
                throw new LogicException("Page not found, wrong url or ip?");
            } else {
                throw new LogicException("Request not successfull, code http : " . $response->getStatusCode());
            }
        }
        // Zyxel doesn't return a error code if wrong password, so we need to check the content
        if (strstr($response->getContent(), "Incorrect password, please try again")) {
            throw new LogicException("Wrong password");
        }
        // Zyxel doesn't return a error code if a user is already connected, so we need to check the content
        if (strstr($response->getContent(), "If a user is logged in already, other users will not be able to access the webpage")) {
            throw new LogicException('a user is already connected to the device');
        }
        return true;
    }
    private function checkLogoutZyxel(ResponseInterface $response): bool
    {
        // Check StatusCode
        if ($response->getStatusCode() != 200) {
            if ($response->getStatusCode() == 404) {
                throw new LogicException("Page not found, wrong url or ip?");
            } else {
                throw new LogicException("Request not successfull, code http : " . $response->getStatusCode());
            }
        }
        // Zyxel after logout redirect to the login page, so if we found "SIGN IN", "Log in" and "Password" in the response it seems be ok
        if (strstr($response->getContent(), "SIGN IN") && strstr($response->getContent(), "Log in") && strstr($response->getContent(), "Password")) {
            return true;
        }
    }
    private function launchCommand(InputInterface $input, OutputInterface $output, string $cookieAuth, string $baseUrl)
    {
        $zyxelDevice = ($input->getOption('device')) ? $input->getOption('device') : $this->zyxelDevice;
        // Try to parse the command line
        try {
            $commandToLaunch = ZyxelCommandParser::parse($zyxelDevice, $input->getArgument('cmd'));
        } catch (ZyxelCommandParserException $ex) {
            $output->writeln([
                'Error while parsing the command line : ',
                $ex->getMessage()
            ]);
            return false;
        }

        $response = $this->client->request(
            $commandToLaunch['method'],
            $baseUrl . $commandToLaunch['url'],
            [
                'body' => $commandToLaunch['params'],
                'headers' => array_merge(
                    $commandToLaunch['headers'],
                    ['Set-Cookie' =>  $cookieAuth]
                )
            ]
        );

        // We wait 2 seconds in order to let the router do the action
        sleep(2);
        return true;
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseUrl = 'http://' . $this->zyxelIp;
        // If an Ip has been set, then override the value from configuration
        if ($input->getOption('ip')) {
            $baseUrl = 'http://' . $input->getOption('ip');
        }
        $zyxelPassword = ($input->getOption('password')) ? $input->getOption('password') : $this->zyxelPassword;

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
        // Check if login is OK
        try {
            $this->checkLoginZyxel($response);
        } catch (LogicException $ex) {
            $output->writeln([
                'Error while logging in : ',
                $ex->getMessage()
            ]);
            return Command::FAILURE;
        }
        // Retrieve the secure token in cookies
        $cookies = $response->getHeaders()['set-cookie'][0];
        $cookieAuth = explode(";", $cookies)[0];

        // Second, We need to launch the command
        $output->writeln([
            '============',
            '2/ Launch the command',
        ]);
        $resultCommand = $this->launchCommand($input, $output, $cookieAuth, $baseUrl);

        // Finally, We need to logout properly, otherwise the router will block the acces to the GUI
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

        // Check if logout is OK
        try {
            if ($this->checkLogoutZyxel($response)) {
                if ($resultCommand === true) {
                    return Command::SUCCESS;
                } else {
                    return Command::INVALID;
                }
            }
        } catch (LogicException $ex) {
            $output->writeln([
                'Error while logging out : ',
                $ex->getMessage(),
                'Caution : if the program is still logging in, you may reboot the router in order to reset the session.'
            ]);
            return Command::FAILURE;
        }
    }
}
