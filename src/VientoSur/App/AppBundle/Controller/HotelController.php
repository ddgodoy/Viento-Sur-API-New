<?php

namespace VientoSur\App\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use VientoSur\App\AppBundle\Entity\Passengers;
use VientoSur\App\AppBundle\Entity\Reservation;
use VientoSur\App\AppBundle\Services\Distribution;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * Hotel controller.
 *
 * @Route("/hotel")
 */
class HotelController extends Controller
{

    public $session;

    /**
     *
     * @Route("/index/", name="hotel_index")
     * @Method("GET")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        return array();
    }

    /**
     * Autocomplete
     *
     * @Route("/autocomplete/{type}", name="despegar_autocomplete")
     * @Method("GET")
     */
    public function autoCompleteHotelCountryAction($type, Request $request)
    {
        $query = $request->get('query');
        $urlParams = [
            'query' => $query,
            'product' => $type,
            'locale' => 'es',
            'city_result' => '10'
        ];
        $despegar = $this->get('despegar');
        $result = $despegar->autocomplete($urlParams);

        foreach ($result as $item) {
            $city = Array();
            $city["value"] = $item["description"];
            $city["data"] = $item["code"];
            $response[] = $city;
        }
        return new JsonResponse(
            array(
                "suggestions" => $response
            )
        );
    }

    /**
     *
     * @Route("/send/hotels/process-search", name="viento_sur_process_search_hotels")
     * @Method("POST")
     */
    public function sendHotelsProcessSearch(Request $request)
    {
        if ($this->getParameter('is_test')) {
            $destinationText = 'Buenos Aires, Ciudad de Buenos Aires, Argentina';
            $destination = 982;
        } else {
            $destinationText = $request->get('autocomplete');
            $destination = $request->get('cityInput');
        }

        list($day, $month, $year) = explode("/", $request->get('start'));
        $fromCalendarHotel = $year . '-' . $month . '-' . $day;

        list($day, $month, $year) = explode("/", $request->get('end'));
        $toCalendarHotel = $year . '-' . $month . '-' . $day;

        $habitacionesCant = $request->get('habitacionesCant');
        $distribution = '';

        if ($habitacionesCant == 1) {
            $distribution = $request->get('adultsSelector');
            $childrens = $request->get('childrenRoomSelector');
            $childAges = '';
            for ($i = 1; $i <= $childrens; $i++) {
                $childAges .= '-' . $request->get('childAgeSelector-' . $i);
            }
            $distribution .= $childAges;
        } else {
            for ($h = 1; $h <= $habitacionesCant; $h++) {
                $adults = $request->get('adultsSelector' . $h);
                $childrens = $request->get('childrenRoomSelector' . $h);
                $childAges = '';
                for ($i = 1; $i <= $childrens; $i++) {
                    $childAges .= '-' . $request->get('childAgeSelector-' . $h . '-' . $i);
                }
                $distribution .= (($h > 1) ? '!' : '') . $adults . $childAges;
            }
        }

        $session = $request->getSession();
        $session->set('checkin_date', $request->get('start'));
        $session->set('checkout_date', $request->get('end'));
        $session->set('distribution', $distribution);
        $session->set('destination', [
            'text' => $destinationText,
            'id' => $destination
        ]);

        return $this->redirect($this->generateUrl('viento_sur_send_hotels', array(
            'destination' => $destination,
            'checkin_date' => $fromCalendarHotel,
            "checkout_date" => $toCalendarHotel,
            'distribution' => $distribution
        )));
    }

    /**
     *
     * @Route("/send/hotels/availabilities/{destination}/{checkin_date}/{checkout_date}/{distribution}", name="viento_sur_send_hotels")
     * @Method("GET")
     */
    public function sendHotelsAvailabilitiesAction($destination, $checkin_date, $checkout_date, $distribution, Request $request)
    {
        $page = $request->query->get('page');
        if (!$page) {
            $page = 1;
            $offset = 0;
        } else {
            $offset = ($page - 1) * 10;
        }

        $sorting = $request->query->get('sorting');
        if (!$sorting) {
            $sorting = 'best_selling_descending';
        }
        $priceRange = $request->query->get('price_range');
        $stars = $request->query->get('stars');
        $paymentType = $request->query->get('payment_type');

        $urlParams = array(
            "country_code" => "AR",
            "checkin_date" => $checkin_date,
            "checkout_date" => $checkout_date,
            "destination" => $destination,
            "distribution" => $distribution,
            "language" => "es",
            "radius" => "200",
            "currency" => "ARS",
            "sorting" => $sorting,
            "classify_roompacks_by" => "none",
            "roompack_choices" => "recommended",
            "offset" => $offset,
            "limit" => "10"
        );

        if ($priceRange) {
            $urlParams['total_price_range'] = $priceRange;
        }
        if ($stars) {
            $urlParams['stars'] = $stars;
        }
        if ($paymentType) {
            $urlParams['payment_type'] = $paymentType;
        }

        $sortMapping = [
            "subtotal_price_ascending" => "Precio: menor a mayor",
            "subtotal_price_descending" => "Precio: mayor a menor",
            "rate_descending" => "Mejor puntuación",
            "location_descending" => "Mejor ubicación",
            "quality_price_descending" => "Mejor precio / calidad",
            "logged_user_descending" => "Descuentos exclusivos",
            "cross_selling_descending" => "Nuevos descuentos",
            "best_selling_descending" => "Más elegidos",
            "stars_descending" => "Estrellas: Mayor a menor",
            "stars_ascending" => "Estrellas: Menor a mayor"
        ];

        $results = $this->get('despegar')->getHotelsAvailabilities($urlParams);

        $restUrl = '?' . http_build_query(array(
                "site" => "AR",
                "checkin_date" => $checkin_date,
                "checkout_date" => $checkout_date,
                "distribution" => $distribution
            ));

        $total = ceil($results['paging']['total'] / 10);

        $viewParams = array(
            'items' => $results,
            'restUrl' => $restUrl,
            'offset' => $offset,
            'limit' => 10,
            'total' => $total,
            'page' => $page,
            'sorting' => $sorting,
            'sortMapping' => $sortMapping
        );

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                array(
                    'html' => $this->renderView('VientoSurAppAppBundle:Hotel:listDetailHotels.html.twig',
                        $viewParams
                    ),
                    'paging' => $results['paging'],
                    'total' => $total,
                    'page' => $page
                )
            );
        } else {
            return $this->render('VientoSurAppAppBundle:Hotel:listHotelsAvailabilities.html.twig', $viewParams);
        }
    }


    /**
     *
     * @Route("/consult", name="viento_sur_app_consult")
     * @Method("POST")
     */
    public function consultAction(Request $request)
    {
        $html = $this->renderView(
            'VientoSurAppAppBundle:Email:contact.html.twig',
            array(
                'txtContactName' => $request->request->get('fullname'),
                'txtEmail' => $request->request->get('email'),
                'txtComments' => $request->request->get('message')
            )
        );

        $this->get('email.service')->sendCommentsEmail($html);

        $request->getSession()->getFlashBag()->add('success', 'El mensaje se ha enviado exitosamente.');
        return new JsonResponse(array("status" => 'success'));
    }


    /**
     *
     * @Route("/show/{idHotel}/availabilities/{restUrl}/latitude/{latitude}/longitude/{longitude}", name="viento_sur_app_app_homepage_show_hotel_id")
     * @Method("GET")
     * @Template()
     */
    public function showHotelIdAvailabilitiesAction(Request $request, $idHotel, $restUrl, $latitude, $longitude)
    {
        $session = $request->getSession();
        $urlParams = array(
            'language' => 'es',
            'currency' => 'ARS'
        );

        $despegar = $this->get('despegar');
        $dispoHotel = $despegar->getHotelsAvailabilitiesDetail($idHotel, $restUrl, $urlParams);
        $session->set('hotelAvailabilities', json_encode($dispoHotel));

        $urlParams = array(
            'ids' => $idHotel,
            'language' => 'es',
            'options' => 'information,amenities,pictures,room_types(pictures,information,amenities)',
            'resolve' => 'merge_info',
            'catalog_info' => 'true'
        );
        $hotelDetails = $despegar->getHotelsDetails($urlParams);

        $session->set('price_detail', $dispoHotel['roompacks'][0]['price_detail']);

        return $this->render('VientoSurAppAppBundle:Hotel:showHotelIdAvailabilities.html.twig', array(
                'dispoHotel' => $dispoHotel,
                'hotelDetails' => $hotelDetails,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'idHotel' => $idHotel,
                'restUrl' => $restUrl
            )
        );
    }

    /**
     * Lists all Company entities.
     *
     * @Route("/show/details/{idHotel}", name="viento_sur_app_app_homepage_show_hotel_photo")
     * @Method("GET")
     * @Template()
     */
    public function detailsHotelListForIdAction(Request $request, $idHotel)
    {

        $hotelUrl = "https://api.despegar.com/v3/hotels?ids=" . $idHotel . "&language=es&options=pictures&resolve=merge_info&catalog_info=true";
        $hotel = $this->cUrlExecAction($hotelUrl);
        $hotelDetails = json_decode($hotel, true);

        return array(
            'hotelDetails' => $hotelDetails
        );
    }

    /**
     * @Route("/booking/hotel/send/booking", name="viento_sur_app_app_homepage_send_hotel_booking")
     * @Method("POST")
     */
    public function sendHotelBookingAction(Request $request)
    {

        $postParams = array(
            "source" => array(
                "country_code" => "AR"
            ),
            "reservation_context" => array(
                "context_language" => "es",
                "shown_currency" => "ARS",
                "threat_metrix_id" => "25",
                "agent_code" => 'AG32502',
                "client_ip" => $request->getClientIp(),
                "user_agent" => $request->headers->get('User-Agent')
            ),
            "keys" => array(
                "availability_token" => $request->get('availability_token')
            )
        );

        $formUrl = $this->get('despegar')->postHotelsBookings($postParams);

        return $this->redirect($this->generateUrl('viento_sur_app_boking_hotel_pay', array(
            'formUrl' => $formUrl["next_step_url"],
            'booking_id' => $formUrl["id"],
            "roompack_choice" => $request->get('roompack_choice')
        )));
    }

    /**
     *
     * @Route("/booking/pay/", name="viento_sur_app_boking_hotel_pay")
     * @Template()
     */
    public function bookingHotelPayAction(Request $request)
    {

        $session = $request->getSession();
        $priceDetail = $session->get('price_detail');
        $roompackChoice = $request->get('roompack_choice');
        $hotelAvailabilities = json_decode($session->get('hotelAvailabilities'));

        $roompack = null;
        foreach ($hotelAvailabilities->roompacks as $item) {
            if ($item->choice = $roompackChoice) {
                $roompack = $item;
                break;
            }
        }

        $formUrl = $request->get('formUrl');
        $bookingId = $request->query->get('formUrl');
        $booking_id = $request->get('booking_id');

        $session->set('booking-id', $bookingId);

        $despegar = $this->get('despegar');
        $sessionForm = $request->getSession();
        $sessionForm->set('url_detail_form', $despegar->getHotelsBookingsNextStepUrl($bookingId));

        if ($request->getMethod() == 'GET') {
            $formBooking = $despegar->hotelsBookingsNextStep($bookingId);
            $session->set('formBooking', json_encode($formBooking));
        } else {
            $formBooking = json_decode($session->get('formBooking'), true);
            //$session->remove('formBooking');
        }

        /* start form */
        $formNewPay = $this->createFormBuilder($formBooking);
        $formHelper = $this->get('form_helper');
        $formNewPay = $formHelper->initForm($formBooking, $formNewPay, $roompackChoice, $roompack->payment_methods);
        $formNewPaySend = $formNewPay->getForm();

        if ($request->getMethod() == 'POST') {

            $formNewPaySend->handleRequest($request);

            if ($formNewPaySend->isValid()) {

                $formNewPaySend = $formNewPaySend->getData();
                $session->set('email', $formNewPaySend['email']);

                //procesar formulario recibido
                $response = $despegar->dVault($formNewPaySend);
                $status = 'ok';
                $detail = [];
                if ($response && isset($response->secure_token)) {
                    //obtengo los valores ya seteados según la selección
                    $fillData = $formHelper->fillFormData($formBooking, $formNewPaySend);
                    $selectedPack = $formHelper->getSelectedPack();
                    $form_id_booking = $selectedPack['id'];

                    $patchParams = [];
                    $patchParams['payment_method_choice'] = $formNewPaySend['paymentMethod'];
                    $patchParams['secure_token_information'] = array('secure_token' => $response->secure_token);
                    $patchParams['form'] = $fillData;

                    $detail = $despegar->patchHotelsBookings($bookingId, $form_id_booking, $patchParams);
                    if (isset($detail['code'])) {
                        $status = 'patch';
                    }

                    if ($status == 'ok') {
                        $em = $this->getDoctrine()->getManager();

                        $reservation = new Reservation();
                        $reservation->setHotelId($hotelAvailabilities->hotel->id);
                        $reservation->setReservationId($detail['reservation_id']);
                        $reservation->setTotalPrice($priceDetail['total']);
                        $reservation->setCardType($formNewPaySend['card_code']);
                        $reservation->setHolderName($formNewPaySend['owner_name']);
                        $reservation->setPhoneNumber($formNewPaySend['country_code0'] . '-' . $formNewPaySend['area_code0'] . '-' . $formNewPaySend['number0']);
                        $reservation->setEmail($formNewPaySend['email']);
                        $reservation->setComments($formNewPaySend['comment']);
                        $checkin = explode("/", $request->getSession()->get('checkin_date'));
                        $checkout = explode("/", $request->getSession()->get('checkout_date'));
                        $reservation->setCheckin(new \DateTime($checkin[1] . '/' . $checkin[0] . '/' . $checkin[2]));
                        $reservation->setCheckout(new \DateTime($checkout[1] . '/' . $checkout[0] . '/' . $checkout[2]));
                        $em->persist($reservation);

                        foreach ($fillData['passengers'] as $key => $value) {
                            $passenger = new Passengers();
                            $passenger->setName($formNewPaySend['first_name' . $key]);
                            $passenger->setLastName($formNewPaySend['last_name' . $key]);
                            $passenger->setDocument($formNewPaySend['document_number' . $key]);
                            $passenger->setReservation($reservation);
                            $em->persist($passenger);
                        }
                        $em->flush();

                        $hotelDetails = $this->get('despegar')->getHotelsDetails(array(
                            'ids' => $hotelAvailabilities->hotel->id,
                            'language' => 'es',
                            'options' => 'information,amenities,pictures,room_types(pictures,information,amenities)',
                            'resolve' => 'merge_info',
                            'catalog_info' => 'true'
                        ));
                        $hotelDetails = (is_array($hotelDetails)) ? $hotelDetails[0] : null;

                        $reservationDetails = $this->get('despegar')->getReservationDetails(
                            $detail['reservation_id'],
                            array(
                                'email' => 'info@vientosur.net',
                                'language' => 'es',
                                'site' => 'AR'
                            ), $this->getParameter('is_test')
                        );

                        //envío de correo
                        try {
                            if ($request->getSession()->get('email')) {
                                $this->get('email.service')->sendBookingEmail($request->getSession()->get('email'), array(
                                    'hotelDetails' => $hotelDetails,
                                    'reservationDetails' => $reservationDetails,
                                    'detail' => $detail,
                                    'hotelId' => $hotelAvailabilities->hotel->id,
                                    'internal' => $reservation,
                                    'pdf' => false
                                ));
                            }
                        } catch (\Exception $e) {
                            $this->get('logger')->error('Booking email error');
                        }

                        return $this->render('VientoSurAppAppBundle:Hotel:payHotelBooking.html.twig', array(
                            'hotelDetails' => $hotelDetails,
                            'reservationDetails' => $reservationDetails,
                            'detail' => $detail,
                            'hotelId' => $hotelAvailabilities->hotel->id,
                            'internal' => $reservation,
                            'status' => $status,
                            'pdf' => false
                        ));
                    }
                } else {
                    $status = 'dvault';
                }

                return $this->redirect($this->generateUrl('viento_sur_app_booking_hotel_summary', array(
                    'status' => $status,
                    'hotel_id' => $hotelAvailabilities->hotel->id,
                    'detail' => $detail,
                    'email' => $formNewPaySend['email']
                )));
            }
        }

        $selectedPack = $formHelper->getSelectedPack();
        $formChoice = $formBooking['dictionary']['form_choices'][$selectedPack['form_choice']];

        $paymentMethods = $roompack->payment_methods;
        $paymentMethods2 = json_decode('{"payment_methods": [{
          "choice": "7",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 209.45,
            "installment": 209.45,
            "total_interest_amount": 0,
            "discount": 0
          },
          "card_ids": [
            "AR-VI-*-CREDIT",
            "AR-CA-*-CREDIT",
            "AR-AX-*-CREDIT",
            "AR-DC-*-CREDIT",
            "AR-CL-*-CREDIT",
            "AR-TN-*-CREDIT",
            "AR-NV-*-CREDIT"
          ],
          "installment_quantity": 1,
          "with_bank_interest": false
        },
        {
          "choice": "10",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 209.45,
            "installment": 69.82,
            "total_interest_amount": 0,
            "discount": 0
          },
          "card_ids": [
            "AR-VI-RIO-CREDIT",
            "AR-AX-RIO-CREDIT",
            "AR-VI-MAC-CREDIT",
            "AR-AX-MAC-CREDIT",
            "AR-CA-MAC-CREDIT",
            "AR-VI-HIP-CREDIT",
            "AR-CA-ITU-CREDIT",
            "AR-VI-ITU-CREDIT",
            "AR-VI-ICBC-CREDIT",
            "AR-VI-PRO-CREDIT",
            "AR-VI-PAT-CREDIT",
            "AR-AX-PAT-CREDIT",
            "AR-CA-PAT-CREDIT",
            "AR-VI-SUP-CREDIT",
            "AR-CA-SUP-CREDIT",
            "AR-VI-CIU-CREDIT",
            "AR-CA-CIU-CREDIT",
            "AR-DC-*-CREDIT",
            "AR-CA-CS-CREDIT",
            "AR-VI-COM-CREDIT",
            "AR-CA-COM-CREDIT",
            "AR-VI-AZU-CREDIT",
            "AR-CA-BERSA-CREDIT",
            "AR-VI-BERSA-CREDIT",
            "AR-VI-BLP-CREDIT",
            "AR-CL-CHA-CREDIT",
            "AR-VI-CHA-CREDIT",
            "AR-CA-NBSF-CREDIT",
            "AR-VI-NBSF-CREDIT",
            "AR-VI-SAENZ-CREDIT",
            "AR-CA-SCR-CREDIT",
            "AR-VI-SCR-CREDIT",
            "AR-CA-SJU-CREDIT",
            "AR-VI-SJU-CREDIT",
            "AR-VI-TUC-CREDIT",
            "AR-CS-*-CREDIT",
            "AR-VI-BTDF-CREDIT",
            "AR-CA-BLP-CREDIT",
            "AR-CA-CHA-CREDIT",
            "AR-CA-TUC-CREDIT",
            "AR-VI-SHO-CREDIT",
            "AR-VI-PVC-CREDIT",
            "AR-CA-AZU-CREDIT",
            "AR-VIS-BCO-CREDIT"
          ],
          "installment_quantity": 3,
          "with_bank_interest": false
        },
        {
          "choice": "6",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 209.45,
            "installment": 34.91,
            "total_interest_amount": 0,
            "discount": 0
          },
          "card_ids": [
            "AR-VI-RIO-CREDIT",
            "AR-AX-RIO-CREDIT",
            "AR-VI-MAC-CREDIT",
            "AR-AX-MAC-CREDIT",
            "AR-CA-MAC-CREDIT",
            "AR-VI-HIP-CREDIT",
            "AR-CA-ITU-CREDIT",
            "AR-VI-ITU-CREDIT",
            "AR-VI-ICBC-CREDIT",
            "AR-VI-PRO-CREDIT",
            "AR-VI-PAT-CREDIT",
            "AR-AX-PAT-CREDIT",
            "AR-CA-PAT-CREDIT",
            "AR-VI-SUP-CREDIT",
            "AR-CA-SUP-CREDIT",
            "AR-VI-CIU-CREDIT",
            "AR-CA-CIU-CREDIT",
            "AR-DC-*-CREDIT",
            "AR-CA-CS-CREDIT",
            "AR-VI-COM-CREDIT",
            "AR-CA-COM-CREDIT",
            "AR-VI-AZU-CREDIT",
            "AR-CA-BERSA-CREDIT",
            "AR-VI-BERSA-CREDIT",
            "AR-VI-BLP-CREDIT",
            "AR-CL-CHA-CREDIT",
            "AR-VI-CHA-CREDIT",
            "AR-CA-NBSF-CREDIT",
            "AR-VI-NBSF-CREDIT",
            "AR-VI-SAENZ-CREDIT",
            "AR-CA-SCR-CREDIT",
            "AR-VI-SCR-CREDIT",
            "AR-CA-SJU-CREDIT",
            "AR-VI-SJU-CREDIT",
            "AR-VI-TUC-CREDIT",
            "AR-CS-*-CREDIT",
            "AR-VI-BTDF-CREDIT",
            "AR-CA-BLP-CREDIT",
            "AR-CA-CHA-CREDIT",
            "AR-CA-TUC-CREDIT",
            "AR-VI-SHO-CREDIT",
            "AR-VI-PVC-CREDIT",
            "AR-CA-AZU-CREDIT",
            "AR-VIS-BCO-CREDIT"
          ],
          "installment_quantity": 6,
          "with_bank_interest": false
        },
        {
          "choice": "13",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 209.45,
            "installment": 23.27,
            "total_interest_amount": 0,
            "discount": 0
          },
          "card_ids": [
            "AR-VI-PRO-CREDIT",
            "AR-VI-SUP-CREDIT",
            "AR-CA-SUP-CREDIT",
            "AR-DC-*-CREDIT",
            "AR-VI-COM-CREDIT",
            "AR-CA-COM-CREDIT",
            "AR-VI-BLP-CREDIT",
            "AR-CL-CHA-CREDIT",
            "AR-VI-CHA-CREDIT",
            "AR-CA-BLP-CREDIT",
            "AR-CA-CHA-CREDIT"
          ],
          "installment_quantity": 9,
          "with_bank_interest": false
        },
        {
          "choice": "5",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 209.45,
            "installment": 17.45,
            "total_interest_amount": 0,
            "discount": 0
          },
          "card_ids": [
            "AR-VI-ICBC-CREDIT",
            "AR-VI-PRO-CREDIT",
            "AR-VI-SUP-CREDIT",
            "AR-CA-SUP-CREDIT",
            "AR-VI-CIU-CREDIT",
            "AR-CA-CIU-CREDIT",
            "AR-DC-*-CREDIT",
            "AR-VI-COM-CREDIT",
            "AR-CA-COM-CREDIT",
            "AR-VI-AZU-CREDIT",
            "AR-VI-BLP-CREDIT",
            "AR-CL-CHA-CREDIT",
            "AR-VI-CHA-CREDIT",
            "AR-VI-ROS-CREDIT",
            "AR-VI-SAENZ-CREDIT",
            "AR-CA-BLP-CREDIT",
            "AR-CA-CHA-CREDIT",
            "AR-VI-PVC-CREDIT",
            "AR-CA-AZU-CREDIT",
            "AR-VIS-BCO-CREDIT"
          ],
          "installment_quantity": 12,
          "with_bank_interest": false
        },
        {
          "choice": "3",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 228.2,
            "installment": 76.07,
            "total_interest_amount": 18.75,
            "discount": 0
          },
          "card_ids": [
            "AR-VI-*-CREDIT",
            "AR-CA-*-CREDIT",
            "AR-CL-*-CREDIT",
            "AR-CA-HSBC-CREDIT",
            "AR-VI-HSBC-CREDIT"
          ],
          "installment_quantity": 3,
          "with_bank_interest": false
        },
        {
          "choice": "11",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 228.76,
            "installment": 76.25,
            "total_interest_amount": 19.31,
            "discount": 0
          },
          "card_ids": [
            "AR-AX-*-CREDIT"
          ],
          "installment_quantity": 3,
          "with_bank_interest": false
        },
        {
          "choice": "2",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 243.17,
            "installment": 40.53,
            "total_interest_amount": 33.72,
            "discount": 0
          },
          "card_ids": [
            "AR-VI-*-CREDIT",
            "AR-CA-*-CREDIT",
            "AR-CL-*-CREDIT",
            "AR-CA-HSBC-CREDIT",
            "AR-VI-HSBC-CREDIT"
          ],
          "installment_quantity": 6,
          "with_bank_interest": false
        },
        {
          "choice": "8",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 244.22,
            "installment": 40.7,
            "total_interest_amount": 34.77,
            "discount": 0
          },
          "card_ids": [
            "AR-AX-*-CREDIT"
          ],
          "installment_quantity": 6,
          "with_bank_interest": false
        },
        {
          "choice": "9",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 280.98,
            "installment": 23.41,
            "total_interest_amount": 71.53,
            "discount": 0
          },
          "card_ids": [
            "AR-VI-*-CREDIT",
            "AR-CA-*-CREDIT",
            "AR-CL-*-CREDIT",
            "AR-CA-HSBC-CREDIT",
            "AR-VI-HSBC-CREDIT"
          ],
          "installment_quantity": 12,
          "with_bank_interest": false
        },
        {
          "choice": "1",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 284.75,
            "installment": 23.73,
            "total_interest_amount": 75.3,
            "discount": 0
          },
          "card_ids": [
            "AR-AX-*-CREDIT"
          ],
          "installment_quantity": 12,
          "with_bank_interest": false
        },
        {
          "choice": "4",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 318.05,
            "installment": 17.67,
            "total_interest_amount": 108.6,
            "discount": 0
          },
          "card_ids": [
            "AR-VI-*-CREDIT",
            "AR-CA-*-CREDIT",
            "AR-CL-*-CREDIT",
            "AR-CA-HSBC-CREDIT",
            "AR-VI-HSBC-CREDIT"
          ],
          "installment_quantity": 18,
          "with_bank_interest": false
        },
        {
          "choice": "12",
          "type": "credit_card",
          "amounts": {
            "currency": "USD",
            "total": 323.92,
            "installment": 18,
            "total_interest_amount": 114.47,
            "discount": 0
          },
          "card_ids": [
            "AR-AX-*-CREDIT"
          ],
          "installment_quantity": 18,
          "with_bank_interest": false
        }
      ]}')->payment_methods;

        //procesado de métodos de pago agrupados por Banco
        $cardsGroup = [];
        foreach ($paymentMethods as $pm) {
            $temp = [];
            if (isset($pm->card_ids)) {
                foreach ($pm->card_ids as $cardId) {
                    $cardParts = explode("-", $cardId);
                    $bank = $cardParts[2];
                    $temp[$bank][] = $cardId;
                }
                $cardsGroup[$pm->choice] = $temp;
            }
        }

        return array(
            'formBooking' => $formBooking,
            'formChoice' => $formChoice,
            'price_detail' => $priceDetail,
            'formUrl' => $formUrl,
            'roompack_choice' => $roompackChoice,
            'booking_id' => $booking_id,
            'formNewPay' => $formNewPaySend->createView(),
            'paymentMethods' => $paymentMethods,
            'paymentMethods2' => $paymentMethods2,
            'rooms' => $roompack->rooms,
            'cardsGroup' => $cardsGroup,
            'errors' => $formNewPaySend->getErrors()
        );
    }

    /**
     *
     * @Route("/booking/summary/", name="viento_sur_app_booking_hotel_summary")
     */
    public function payHotelBookingAction(Request $request)
    {
        $status = $request->get('status');
        $detail = $request->get('detail');
        $hotelId = $request->get('hotel_id');

        $hotelDetails = null;
        if ($status == 'ok') {
            $urlParams = array(
                'ids' => $hotelId,
                'language' => 'es',
                'options' => 'information,amenities,pictures,room_types(pictures,information,amenities)',
                'resolve' => 'merge_info',
                'catalog_info' => 'true'
            );
            $hotelDetails = $this->get('despegar')->getHotelsDetails($urlParams);
            $hotelDetails = (is_array($hotelDetails)) ? $hotelDetails[0] : null;
            return $this->render('VientoSurAppAppBundle:Hotel:payHotelBooking.html.twig', array(
                'status' => $status,
                'detail' => $detail,
                'hotelId' => $hotelId,
                'hotelDetails' => $hotelDetails
            ));
        } else {
            return $this->render('VientoSurAppAppBundle:Hotel:errorHotelBooking.html.twig', array(
                'status' => $status,
                'detail' => $detail,
                'hotelId' => $hotelId,
                'hotelDetails' => $hotelDetails
            ));
        }
    }

    /**
     * @Route("/booking/pdf/", name="viento_sur_app_booking_hotel_pdf")
     */
    public function showPdfBookingAction(Request $request)
    {
        $detail = $request->get('detail');
        $hotelId = $request->get('hotel_id');
        $email = $request->get('email');
        $reservationId = $request->get('reservation_id');

        $em = $this->getDoctrine()->getManager();
        $reservation = $em->getRepository('VientoSurAppAppBundle:Reservation')->findOneById($reservationId);

        $hotelDetails = $this->get('despegar')->getHotelsDetails(array(
            'ids' => $hotelId,
            'language' => 'es',
            'options' => 'information,amenities,pictures,room_types(pictures,information,amenities)',
            'resolve' => 'merge_info',
            'catalog_info' => 'true'
        ));
        $reservationDetails = $this->get('despegar')->getReservationDetails($detail['reservation_id'], array(
            'email' => 'info@vientosur.net',
            'language' => 'es',
            'site' => 'AR'
        ), $this->getParameter('is_test'));


        $html = $this->renderView('VientoSurAppAppBundle:Pdf:booking.html.twig', array(
            'hotelDetails' => $hotelDetails[0],
            'reservationDetails' => $reservationDetails,
            'detail' => $detail,
            'hotelId' => $hotelId,
            'internal' => $reservation,
            'pdf' => true
        ));

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="reservacion.pdf"'
            )
        );
    }

    private function cUrlExecAction($url)
    {
        $cSession = curl_init();
        curl_setopt($cSession, CURLOPT_URL, $url);
        curl_setopt($cSession, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cSession, CURLOPT_HTTPHEADER, array('X-ApiKey:2864680fe4d74241aa613874fa20705f'));
        curl_setopt($cSession, CURLOPT_HEADER, false);
        curl_setopt($cSession, CURLOPT_ACCEPT_ENCODING, "");
        //step3
        $results = curl_exec($cSession);
        //step4
        curl_close($cSession);

        return $results;
    }
}
