<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;


class ApiController extends AbstractController
{
    #[Route('/api', name: 'app_api', methods: ['POST'])]
    public function index(KernelInterface $kernel, Request $request): JsonResponse
    {
        if (!$request->get('cmd') || !$request->get('args')) {
            throw new BadRequestException('cmd and args parameters need to be set');
        }

        $cmd = $request->get('cmd');
        $args = $request->get('args');

        if (!is_array($args)) {
            throw new BadRequestException('args need to be an array of arguments');
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $inputArray = ['command' => 'app:zyxel', 'cmd' => array_merge([$cmd], $args)];

        // Check options
        if ($request->get('zyxel_ip') && $request->get('zyxel_password')) {
            $inputArray['--ip'] = $request->get('zyxel_ip');
            $inputArray['--password'] = $request->get('zyxel_password');
        }
        if ($request->get('zyxel_device')) {
            $inputArray['--device'] = $request->get('zyxel_device');
        }

        $input = new ArrayInput($inputArray);

        // You can use NullOutput() if you don't need the output
        $output = new BufferedOutput();
        $application->run($input, $output);

        // return the output, don't use if you used NullOutput()
        $content = $output->fetch();
        // If we don't have the conclusion section, then we got an error
        if (!preg_match('/4\/ Conclusion/', $content)) {
            throw new \Exception('Command failed, content of cmd : ' . $content);
        }

        return $this->json([
            'message' => $content,
            'path' => 'src/Controller/ApiController.php',
        ]);
    }
}
