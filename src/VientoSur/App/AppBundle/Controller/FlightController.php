<?php


namespace VientoSur\App\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use VientoSur\App\AppBundle\Entity\Airlines;
use VientoSur\App\AppBundle\Entity\AirlineAlliance;

/**
 * Flight Controller
 *
 * @Route("/vuelos")
 */
class FlightController extends Controller
{
    /**
     * @Route("/send/flights/process-search", name="viento_sur_process_search_flights")
     * @Method("POST")
     */
    public function sendFlightsProcessSearch(Request $request)
    {
        if ($this->getParameter('is_test')) {
            $fromText = 'Buenos Aires, Argentina';
            $toText = 'Miami, Estados Unidos';
            $from = 'BUE';
            $to = 'MIA';
        } else {
            $fromText = $request->get('from-flight');
            $toText = $request->get('to-flight');
            $from = $request->get('originFlight');
            $to = $request->get('destinationFlight');
        }

        list($day, $month, $year) = explode("/", $request->get('start'));
        $fromDate = $year . '-' . $month . '-' . $day;
        list($day, $month, $year) = explode("/", $request->get('end'));
        $toDate = $year . '-' . $month . '-' . $day;

        $adults = $request->get('adultsPassengers');
        $childrens = $request->get('childrenPassengers');
        $childrenQty = 0;
        $infantQty = 0;
        for ($i = 1; $i <= $childrens; $i++) {
            if ($request->get('field-menor-' . $i) == 'A') {
                $infantQty++;
            } else {
                $childrenQty++;
            }
        }

        $session = $request->getSession();
        $session->set('departure_date', $request->get('start'));
        $session->set('return_date', $request->get('end'));
        $session->set('origin_flight', [
            'text' => $fromText,
            'id' => $from
        ]);
        $session->set('destination_flight', [
            'text' => $toText,
            'id' => $to
        ]);

        return $this->redirectToRoute('viento_sur_send_flights', array(
            'departure_date' => $fromDate,
            'return_date' => $toDate,
            'from' => $from,
            'to' => $to,
            'adults' => $adults,
            'childrens' => $childrenQty, // I para presentar infante en asiento, C para representar niño de 2 a 11 años, si hay más de uno, separados por guion "-"
            'infants' => $infantQty //cantidad de infantes en brazos
        ));
    }


    /**
     * @Route("/send/flights/results/{from}/{to}/{departure_date}/{return_date}/{adults}/{childrens}/{infants}", name="viento_sur_send_flights")
     * @Method("GET")
     */
    public function sendFlightsItinerariesAction($from, $to, $departure_date, $return_date, $adults, $childrens, $infants, Request $request)
    {
        $urlParams = [
            "site" => "AR",
            "departure_date" => $departure_date,
            "return_date" => $return_date,
            "language" => "es",
            "from" => $from,
            "to" => $to,
            "adults" => $adults,
            "children" => $childrens,
            "infants" => $infants,
            "offset" => '0',
            "limit" => "10",
            "currency" => "ARS"
        ];

        $results = $this->get('despegar')->getFlightItineraries($urlParams);

        if (isset($results['items'])) {
            $airlines = [];
            foreach ($results['items'] as $item) {
                if (isset($item['validating_carrier'])) {
                    if (!in_array($item['validating_carrier'], $airlines)) {
                        $airlines[] = $item['validating_carrier'];
                    }
                }
                foreach ($item['outbound_choices'] as $outbound) {
                    foreach ($outbound['segments'] as $segment) {
                        if (!in_array($segment['airline'], $airlines)) {
                            $airlines[] = $segment['airline'];
                        }
                    }
                }
                foreach ($item['inbound_choices'] as $inbound) {
                    foreach ($inbound['segments'] as $segment) {
                        if (!in_array($segment['airline'], $airlines)) {
                            $airlines[] = $segment['airline'];
                        }
                    }
                }
            }
        }

        $em = $this->getDoctrine()->getManager();
        $airlineResults = $em->getRepository('VientoSurAppAppBundle:Airlines')->findAirlinesIn($airlines);
        $airlineData = [];
        foreach ($airlineResults as $ar) {
            $airlineData[$ar->getId()] = $ar->getName();
        }

        return $this->render('VientoSurAppAppBundle:Flight:listFlightsItineraries.html.twig', array(
            'flightMenu' => true,
            'items' => $results,
            'airlineNames' => $airlineData
        ));
    }

    /**
     *
     * @Route("/autocomplete-flight", name="flight_autocomplete")
     * @Method("GET")
     */
    public function autoCompleteFlightAction(Request $request)
    {
        $type = 'HOTELS';
        $urlParams = [
            'query' => $request->get('query'),
            'product' => $type,
            'locale' => 'es-AR',
            'city_result' => '5',
            'airport_result' => '5'
        ];

        $results = $this->get('despegar')->autocomplete($urlParams);
        $response = [];
        if ($results && !isset($results['code'])) {
            foreach ($results as $item) {
                $category = '';
                if ($item['facet'] == 'CITY') {
                    $category = 'Ciudades';
                } else if ($item['facet'] == 'AIRPORT') {
                    $category = 'Aereopuertos';
                }
                $response[] = [
                    'value' => $item["description"],
                    'data' => [
                        'category' => $category,
                        'id' => $item["facet"] . '-' . $item["code"]
                    ]
                ];
            }
        }
        return new JsonResponse(array("suggestions" => $response, 'query' => $request->get('query'), 'test' => $results));
    }

    /**
     * @Route("/load-arlines", name="load_airlines")
     * @Method("GET")
     */
    public function loadAirlinesAction()
    {
        $results = $this->get('despegar')->getFlightAirlines([
            "site" => "AR"
        ]);

        $em = $this->getDoctrine()->getManager();
        $arr = [];
        for ($i = 0; $i < count($results); $i++) {
            $airline = $results[$i];
            if (!in_array($airline['id'], $arr)) {
                $objAirline = new Airlines();
                $objAirline->setId($airline['id']);
                $arr[] = $airline['id'];
                $objAirline->setName($airline['name']);
                if (isset($airline['alliance'])) {
                    $ojbAlliance = new AirlineAlliance();
                    $ojbAlliance->setCode($airline['alliance']['id']);
                    $ojbAlliance->setName($airline['alliance']['name']);
                    $ojbAlliance->setAirline($objAirline);
                    $em->persist($ojbAlliance);
                }
                $em->persist($objAirline);
            }
        }
        $em->flush();

        return new JsonResponse(array("result" => "ready"));
    }

    /**
     * @Route("/load-arline-detail", name="load_airline-detail")
     * @Method("GET")
     */
    public function loadAirlineDetailAction()
    {
        $results = $this->get('despegar')->getFlightAirlineDetail('V0', [
            "site" => "AR"
        ]);

        return new JsonResponse(array("result" => $results));
    }

    /**
     * @Route("/booking/flight/send/booking", name="viento_sur_send_flight_booking")
     * @Method("POST")
     */
    public function sendFlightBookingAction(Request $request)
    {
        $fields = $request->request->all();

        $optionId = $fields['option_id'];
        $outbound = $fields['optionsRadiosOut' . $optionId];
        $inbound = $fields['optionsRadiosIn' . $optionId];

        return $this->redirect($this->generateUrl('viento_booking_flight_pay', array(
            'outbound' => $outbound,
            'inbound' => $inbound,
            'item_id' => $fields['item_id']
        )));
    }

    /**
     * @Route("/booking/pay", name="viento_booking_flight_pay")
     */
    public function bookingFlightPayAction(Request $request)
    {
        $params = $request->query->all();

        $urlParams = [
            'itinerary_id' => $params['item_id'],
            'outbound' => $params['outbound'],
            'inbound' => ((empty($params['inbound'])) ? '' : $params['inbound']),
            'language' => 'es',
            'tracker_id' => '',
            'country' => 'AR'
        ];

        $flightService = $this->get('flights_service');

        if ($request->getMethod() == 'GET') {
            $options = [
                'outbound_choice' => $params['outbound']
            ];
            if (isset($params['inbound'])) {
                $options['inbound_choice'] = $params['inbound'];
            }
            $itineraryDetail = $flightService->getItineraryDetail($options, $params['item_id']);

            $booking = $flightService->getCheckoutData($urlParams);
            $request->getSession()->set('flightBooking', json_encode($booking));
            $request->getSession()->set('itineraryDetail', json_encode($itineraryDetail));
        } else {
            $itineraryDetail = json_decode($request->getSession()->get('itineraryDetail'), true);
            $booking = json_decode($request->getSession()->get('flightBooking'), true);
            //$session->remove('formBooking');
        }


        $formNewPay = $this->createFormBuilder($booking);
        $formNewPay = $flightService->initForm($booking, $formNewPay);
        $formNewPaySend = $formNewPay->getForm();

        if ($request->getMethod() == 'POST') {
            $formNewPaySend->handleRequest($request);

            if ($formNewPaySend->isValid()) {
                $formNewPaySend = $formNewPaySend->getData();

                //procesar pago mediante api de dvault
                $dvault = $flightService->processdVault($formNewPaySend);
                $status = 'ok';

                if ($dvault && isset($dvault->secure_token)) {
                    $reservation = $flightService->processReservation($dvault->secure_token, $formNewPaySend, $booking, $request->getClientIp());

                    if (!$reservation) {
                        $status = 'fail';
                    }
                } else {
                    $status = 'dvault';
                }

                return $this->redirectToRoute('viento_sur_app_booking_flight_summary', array(
                    'status' => $status
                ));
            }
        }

        $paymentMethods = [];
        foreach ($itineraryDetail['payment_methods'] as $element) {
            $value = $element['installments'];
            if (isset($element['bank_code'])) {
                $paymentMethods[$value][$element['bank_code']][] = $element;
            } else {
                $paymentMethods[$value]['generic'][] = $element;
            }
        }
        ksort($paymentMethods, SORT_NUMERIC);

        $string = bin2hex(random_bytes(16));
        $riskAnalysis = [
            "a" => [
                'orgId' => $this->getParameter('risk_provider_id_a')
            ],
            "b" => [
                'orgId' => ($this->getParameter('is_test')) ? $this->getParameter('risk_provider_id_b_test') : $this->getParameter('risk_provider_id_b')
            ],
            'sessionId' => $string
        ];

        return $this->render('VientoSurAppAppBundle:Flight:bookingFlightPay.html.twig', array(
            'flightMenu' => true,
            'formNewPay' => $formNewPaySend->createView(),
            'formChoice' => $booking,
            'itineraryDetail' => $itineraryDetail,
            'paymentMethods' => $paymentMethods,
            'riskAnalysis' => $riskAnalysis
        ));
    }

    /**
     *
     * @Route("/booking/summary/", name="viento_sur_app_booking_flight_summary")
     */
    public function payFlightBookingAction(Request $request)
    {
        $status = $request->get('status');

        if ($status == 'ok') {
//            $urlParams = array(
//                'ids' => $hotelId,
//                'language' => 'es',
//                'options' => 'information,amenities,pictures,room_types(pictures,information,amenities)',
//                'resolve' => 'merge_info',
//                'catalog_info' => 'true'
//            );
//            $hotelDetails = $this->get('despegar')->getHotelsDetails($urlParams);
//            $hotelDetails = (is_array($hotelDetails)) ? $hotelDetails[0] : null;
            return $this->render('VientoSurAppAppBundle:Flight:payFlightBooking.html.twig', array(
                'status' => $status,
//                'itineraryDetail' => $itineraryDetail,
//                'internal' => $reservation,
//                'pasengers'=> $passengers,
            ));
        } else {
            return $this->render('VientoSurAppAppBundle:Flight:errorFlightBooking.html.twig', array(
                'status' => $status
            ));
        }
    }
}