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

class IsohuntCrawlerCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('crawler:isohunt:start')

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
        $this->crawlIsohunt($input, $output);
    }

    protected function crawlIsohunt(InputInterface $input, OutputInterface $output){
        $printer = new SymfonyStyle($input, $output);
        $em = $this->getContainer()->get('doctrine')->getManager();
        $client = new Client();
        $rootUrls = [
            "https://isohunt.to/torrents/?iht=1&age=0",
            "https://isohunt.to/torrents/?iht=2&age=0",
            "https://isohunt.to/torrents/?iht=3&age=0",
            "https://isohunt.to/torrents/?iht=4&age=0",
            "https://isohunt.to/torrents/?iht=5&age=0",
            "https://isohunt.to/torrents/?iht=6&age=0",
            "https://isohunt.to/torrents/?iht=7&age=0",
            "https://isohunt.to/torrents/?iht=8&age=0",
            "https://isohunt.to/torrents/?iht=9&age=0"
        ];
        $maxPages = null;
        $page = 0;
        foreach($rootUrls as $rootUrl){
            //  first page, Torrent_Page=0, get max pages along with the info
            if($page == 0){
                $url = $rootUrl.'&Torrent_Page='.$page;
                $crawler = $client->request('GET', $url);
                $crawler->filter('.pagination .last a')->each(function ($node) use (&$maxPages){
                    $lastPaginationHref = $node->attr('href');
                    parse_str($lastPaginationHref, $output);
                    $maxPages = $output['Torrent_page']/40;
                });
            }
            for($page=0;$page<=$maxPages;$page++){
                $url = $rootUrl.'&Torrent_page='.($page*40);
                $printer->section('Crawling page '.$page.' url '.$url);
                $crawler = $client->request('GET', $url);
                $listOfLinks = [];
                $crawler->filterXPath('//td[contains(@class, "title-row")]/a[1]')->each(function ($node) use (&$listOfLinks) {
                    $listOfLinks[] = 'https://isohunt.to'. $node->attr('href');
                });

                $allCompleted = false;
                $responses = [];
                while($allCompleted != true){
                    $promiseArray = $this->sendCrawlPromise($listOfLinks);
                    try{
                        $responses = \Amp\wait(\Amp\all($promiseArray));
                        $allCompleted = true;
                    } catch (\Exception $e){
                        sleep(300); // wait 5 minutes
                    }
                }

                /**
                 * @var int $key
                 * @var Response $response
                 */
                foreach ($responses as $key => $response) {
                    if ($response->getStatus() == 200) {
                        $crawler2 = new Crawler($response->getBody());
                        $name = trim($crawler2->filter('h1.torrent-header')->first()->text());
                        $magnetLink = $crawler2->filter('a.btn-magnet')->first()->attr('href');
                        $seeders = trim($crawler2->filter('span.seeds')->first()->text());
                        $printer->writeln('Found ' . $name);
                        $hash = sha1($name);
                        /** @var Magnet $magnet */
                        $magnet = $this->getContainer()->get('doctrine')
                            ->getRepository('AppBundle:Magnet')
                            ->findOneBy(['hash' => $hash]);
                        if (null == $magnet) {
                            $magnet = new Magnet();
                            $magnet->setHash($hash);
                            $magnet->setName($name);
                            $magnet->setLink($magnetLink);
                            $magnet->setSeeders($seeders);
                        } else {
                            $magnet->setHash($hash);
                            $magnet->setName($name);
                            $magnet->setLink($magnetLink);
                            $magnet->setSeeders($seeders);
                        }
                        $em->persist($magnet);
                        $em->flush();
                    }
                    sleep(1);
                }
                $em->clear();
            }
        }
    }

    protected function sendCrawlPromise($listOfLinks, $options = [ArtaxClient::OP_HOST_CONNECTION_LIMIT => 5]){
        return (new ArtaxClient())->requestMulti($listOfLinks, $options);
    }
}
