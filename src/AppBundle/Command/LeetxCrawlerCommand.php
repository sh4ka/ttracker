<?php

namespace AppBundle\Command;

use Amp\Artax\Response;
use AppBundle\Entity\Magnet;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Goutte\Client;
use Amp\Artax\Client as ArtaxClient;
use Symfony\Component\DomCrawler\Crawler;

class LeetxCrawlerCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('crawler:1337x:start')

            // the short description shown while running "php bin/console list"
            ->setDescription('Start 1337x crawler')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("This command starts the 1337x crawler")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $printer = new SymfonyStyle($input, $output);
        $printer->section(
            'Starting'
        );
        $this->crawl1337x($input, $output);
    }

    protected function crawl1337x(InputInterface $input, OutputInterface $output){
        $printer = new SymfonyStyle($input, $output);
        $em = $this->getContainer()->get('doctrine')->getManager();
        $client = new Client();
        $page = 1;
        $rootUrls = [
            "https://1337x.unblocked.vip/cat/Anime/[page]/",
            "https://1337x.unblocked.vip/cat/Apps/[page]/",
            "https://1337x.unblocked.vip/cat/Documentaries/[page]/",
            "https://1337x.unblocked.vip/cat/Games/[page]/",
            "https://1337x.unblocked.vip/cat/Movies/[page]/",
            "https://1337x.unblocked.vip/cat/Music/[page]/",
            "https://1337x.unblocked.vip/cat/Other/[page]/",
            "https://1337x.unblocked.vip/cat/TV/[page]/",
            "https://1337x.unblocked.vip/cat/XXX/[page]/",
        ];
        $failedLinks = [];
        $maxPages = null;
        foreach($rootUrls as $rootUrl){
            //  first page, get max pages along with the info
            if($page == 1){
                $url = $rootUrl;
                $crawler = $client->request('GET', $url);
                $crawler->filter('li.last a')->each(function ($node) use (&$maxPages){
                    $lastPaginationHref = $node->attr('href');
                    $maxPages = explode('/', $lastPaginationHref)[3];
                });
            }
            for($page=1;$page<=$maxPages;$page++){
                $url = str_replace('[page]', $page, $rootUrl);
                $printer->section('Crawling page '.$page.' url '.$url);
                $crawler = $client->request('GET', $url);
                $listOfLinks = [];
                $crawler->filterXPath('//td[contains(@class, "name")]/a[2]')->each(function ($node) use (&$listOfLinks) {
                    $listOfLinks[] = 'https://1337x.unblocked.vip'. $node->attr('href');
                });

                $allCompleted = false;
                $responses = [];
                while($allCompleted != true){
                    $promiseArray = $this->sendCrawlPromise($listOfLinks);
                    try{
                        $responses = \Amp\wait(\Amp\all($promiseArray));
                        $allCompleted = true;
                    } catch (\Exception $e){
                        sleep(15);
                    }
                }

                /**
                 * @var int $key
                 * @var Response $response
                 */
                foreach ($responses as $key => $response) {
                    if ($response->getStatus() == 200) {
                        $crawler2 = new Crawler($response->getBody());
                        $name = trim($crawler2->filter('div.box-info-heading h1')->first()->text());
                        $seeders = $crawler2->filter('span.seeds')->first()->text();
                        $magnetLink = $crawler2->filter('a.btn-magnet')->first()->attr('href');
                        $printer->writeln('Found ' . $name);
                        $hash = sha1($name);
                        $magnet = $this->getContainer()->get('doctrine')
                            ->getRepository('AppBundle:Magnet')
                            ->findOneBy(['hash' => $hash]);
                        /** @var Magnet $magnet */
                        if (null == $magnet) {
                            $magnet = new Magnet();
                            $magnet->setHash($hash);
                            $magnet->setName($name);
                            $magnet->setSeeders($seeders);
                            $magnet->setLink($magnetLink);
                        } else {
                            $magnet->setHash($hash);
                            $magnet->setName($name);
                            $magnet->setSeeders($seeders);
                            $magnet->setLink($magnetLink);
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
