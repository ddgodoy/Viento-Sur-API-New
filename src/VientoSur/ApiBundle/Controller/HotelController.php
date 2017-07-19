<?php
namespace VientoSur\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as FOSRestBundleAnnotations;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * Class HotelController
 *
 * @package ApiBundel\Controller
 *
 * @FOSRestBundleAnnotations\View()
 * */
class HotelController extends FOSRestController implements ClassResourceInterface
{
    /**
     * Get all destinations
     *
     * @param String $query
     * @return mixed
     *
     * @FOSRestBundleAnnotations\Route("/hotel/destinations/{query}")
     * @ApiDoc(
     *  section="Hotel",
     *  description="Get all destinations",
     *  statusCodes={
     *     200="Returned when successful",
     *     404="Destination not found"
     *  },
     *  tags={
     *   "stable" = "#4A7023",
     *   "v1" = "#ff0000"
     *  }
     * )
     */
    public function getDestinationsAction($query){
        $urlParams = array(
            'query' => $query,
            'product' => 'HOTELS',
            'locale' => 'es',
            'city_result' => '10',
            'hotel_result' => '5'
        );

        $results = $this->get('despegar')->autocomplete($urlParams);
        $response = new JsonResponse($results);

        return $response;
    }
}