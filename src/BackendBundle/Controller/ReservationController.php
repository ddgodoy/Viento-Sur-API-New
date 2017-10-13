<?php

namespace BackendBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Date;
use VientoSur\App\AppBundle\Entity\Reservation;

/**
 * @Route("/{_locale}/dashboard-reservation", requirements={"_locale": "es|en|pt"}, defaults={"_locale": "es"})
 */
class ReservationController extends Controller
{
    /**
     * @param $request
     * @Security("has_role('ROLE_HOTELIER')")
     * @Route("/", name="reservation_list")
     * @Method("GET")
     * @return response
     */
    public function indexAction(Request $request)
    {
        $querySearch = $request->get('query');

        $em = $this->getDoctrine()->getManager();
        $securityContext = $this->container->get('security.context');

        if(!empty($querySearch)){
            $finder = $this->container->get('fos_elastica.finder.app.reservation');
            $query = $finder->createPaginatorAdapter($querySearch);
        }else{
            if ($securityContext->isGranted('ROLE_ADMIN')) {
                $dql = "SELECT r
                    FROM VientoSurAppAppBundle:Reservation r
                    ORDER BY r.id ASC";
            }else{
                $hotel = $em->getRepository('VientoSurAppAppBundle:Hotel')->findOneBy(array(
                    'created_by' => $this->getUser()->getId()
                ));
                $dql = "SELECT r
                    FROM VientoSurAppAppBundle:Reservation r
                    WHERE r.hotelId = ".$hotel->getId()."
                    ORDER BY r.id ASC";
            }
            $query = $em->createQuery($dql);
        }


        $page = $request->query->getInt('page', 1);
        $paginator = $this->get('knp_paginator');
        $items_per_page = $this->getParameter('items_per_page');

        $pagination = $paginator->paginate($query, $page, $items_per_page);

        return $this->render(':admin/reservation:list.html.twig', array(
            'pagination' => $pagination
        ));
    }

    /**
     * @param $entity
     * @Security("has_role('ROLE_HOTELIER')")
     * @Route("/show/{id}", name="reservation_show")
     * @Method("GET")
     * @return response
     */
    public function showAction(Reservation $entity)
    {
        $em = $this->getDoctrine()->getManager();

        if ($entity->getOrigin() == 'despegar'){
            $guests = $em->getRepository('VientoSurAppAppBundle:Passengers')->findBy(array(
               'reservation' => $entity->getId()
            ));
            $reservationDetails = $this->container->get('despegar')->getReservationDetails(
                $entity->getReservationId(),
                array(
                    'email' => 'info@vientosur.net',
                    'language' => 'es',
                    'site' => 'AR'
                ), $this->getParameter('is_test')
            );

            $hotelDetails = $this->container->get('despegar')->getHotelsDetails(array(
                'ids' =>  $entity->getHotelId(),
                'language' => 'es',
                'options' => 'information',
                'resolve' => 'merge_info',
                'catalog_info' => 'true'
            ));
            $hotelDetails = (is_array($hotelDetails)) ? $hotelDetails[0] : null;

            $extraData = array(
                'hotelDetails' => $hotelDetails,
                'reservationDetails' => $reservationDetails,
                'guests' => $guests
            );

        }elseif ($entity->getOrigin() == 'vientosur'){
            $extraData = json_decode($entity->getExtraData());

            $rooms = $em->getRepository('VientoSurAppAppBundle:Room')->findRoomsByIds($extraData->room);
            $this->get('session')->set('rooms', $rooms);

        }

        return $this->render(':admin/reservation:show.html.twig', array(
            'entity' => $entity,
            'extraData' => $extraData
        ));
    }

    /**
     * @Security("has_role('ROLE_HOTELIER')")
     * @Route("/test-reservation", name="test_reservation_new")
     * @Method("GET")
     * @return response
     */
    public function testNewAction()
    {
        $em = $this->getDoctrine()->getManager();

        $extra_data = array(
            'tdc_data' => array(
                'holder_name' => 'phils garcia',
                'dni' => 123456,
                'card_number' => 234564,
                'type_card' => 'mastercard',
                'security_code' => 132,
                'expiration_date' => '10/17'
            ),
            'travelers' => array(
                array(
                    "first_name" => "Phils",
                    "last_name" => "Garcia Quiroz",
                    "room_reference" => null,
                    "document_number" => "29742594"
                ),
                array(
                    "first_name" => "Phils2",
                    "last_name" => "Garcia Quiroz2",
                    "room_reference" => null,
                    "document_number" => "297425942"
                )
            ),
            'room' => array(
                array(
                    'room_id' => 20
                ),
                array(
                    'room_id' => 21
                )
            )
        );

        $entity = new Reservation();
        $entity->setHolderName('phils garcia');
        $entity->setCheckin(new \DateTime("now"));
        $entity->setCheckout(new \DateTime("now"));
        $entity->setPhoneNumber('+584125983290');
        $entity->setEmail('eivanphils@gmail.com');
        $entity->setCardType('CA');
        $entity->setReservationId('13456');
        $entity->setHotelId(23);
        $entity->setTotalPrice(1000);
        $entity->setComments('comentarios');
        $entity->setOrigin('vientosur');
        $entity->setExtraData(json_encode($extra_data));

        $em->persist($entity);
        $em->flush();

        return $this->redirectToRoute('reservation_list');
    }
}