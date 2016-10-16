<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction()
    {
        $repository = $this->getDoctrine()
            ->getRepository('AppBundle:Magnet');
        $query = $repository->createQueryBuilder('m')
            ->getQuery();

        $torrents = $query->setMaxResults(5)->getResult();
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig', [
            'torrents' => $torrents,
        ]);
    }

    /**
     * @Route("/search", name="search")
     */
    public function search(Request $request)
    {
        $search = $request->query->get('q');
        if($search != null){
            $em = $this->getDoctrine()->getManager();
            $query = $em->createQuery(
                "SELECT m
                FROM AppBundle:Magnet m
                WHERE m.name LIKE :title ORDER BY m.seeders DESC"
            )->setParameter('title', $search.'%');

            $magnets = $query->getResult();

            return $this->render('default/index.html.twig', [
                'torrents' => $magnets,
            ]);
        } else {
            return $this->redirect($this->generateUrl('homepage'));
        }
    }
}
