<?php

namespace AppBundle\Command;

use Amp\Artax\Response;
use AppBundle\Entity\Magnet;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Goutte\Client;
use Amp\Artax\Client as ArtaxClient;
use Symfony\Component\DomCrawler\Crawler;

class EztvCrawlerCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('crawler:eztv:start')

            // the short description shown while running "php bin/console list"
            ->setDescription('Start crawler')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("This command starts the crawler")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $printer = new SymfonyStyle($input, $output);
        $printer->section(
            'Starting'
        );
        $this->crawlEztv($input, $output);
    }

    protected function crawlEztv(InputInterface $input, OutputInterface $output){
        $pages = 1500;
        $em = $this->getContainer()->get('doctrine')->getManager();
        $printer = new SymfonyStyle($input, $output);

        $client = new Client();
        $urlsToCrawl = [];

        foreach(range(1, $pages) as $index){
            $printer->section('Crawling page '.$index);
            $url = "https://eztv.ag/page_$index";
            $urlsToCrawl[] = $url;
            $crawler = $client->request('GET', $url);
            $crawler->filter('a')->each(function ($node) use ($printer, $em) {
                if(strstr($node->attr('href'), 'magnet:')){
                    $printer->writeln('Found '.$node->attr('title'));
                    $hash = sha1($node->attr('title'));
                    $magnet = $this->getContainer()->get('doctrine')
                        ->getRepository('AppBundle:Magnet')
                        ->findBy(['hash' => $hash]);
                    if(!$magnet){
                        $magnet = new Magnet();
                        $magnet->setHash($hash);
                        $magnet->setName($node->attr('title'));
                        $magnet->setLink($node->attr('href'));
                        $em->persist($magnet);
                    }
                    $em->flush();
                }
            });
            $em->clear();
        }
    }
}
