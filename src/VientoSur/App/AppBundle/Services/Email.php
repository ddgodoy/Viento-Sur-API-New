<?php

namespace VientoSur\App\AppBundle\Services;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Templating\EngineInterface;

class Email
{
    private $mailer;
    private $templating;
    private $container;
    private $session;

    public function __construct(\Swift_Mailer $mailer, EngineInterface $templating, Container $container, Session $session)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->container = $container;
        $this->session = $session;
    }

    public function sendCommentsEmail($html, $email= 'web@vientosur.net')
    {
        $message = \Swift_Message::newInstance(null)
            ->setSubject("Consulta web Viento Sur")
            ->setFrom($email, $email)
            ->setTo('info@vientosur.net','VientoSur.net')
            ->setBody(
                $html,
                'text/html'
            );

        $this->mailer->send($message);
    }

    public function sendBookingEmail($email, $data)
    {
        $fs = new Filesystem();

        $reservationId = $this->session->get('despegarReservationId');
        $internalId = $this->session->get('reservationInternalId');

        $filePath = $this->container->getParameter('kernel.root_dir') . '/../web/'.$internalId.'.pdf';
        $fs->rename($filePath, 'voucher-vs.pdf');
        $filenName = $this->container->getParameter('kernel.root_dir') . '/../web/voucher-vs.pdf';

        $message = \Swift_Message::newInstance()
            ->setSubject('Viento Sur Operadores Turísticos - Solicitud de compra de Hotel - Número: '.$reservationId)
//            ->setFrom('info@vientosur.net','VientoSur.net')
            ->setFrom("no-replay@vientosur.net", 'Viento Sur Operadores Turísticos')
            ->setTo([$email])
            ->setBody(
                $this->templating->render(
                    '@VientoSurAppApp/layoutEmailHotelPdf.html.twig',
                    $data
                ),
                'text/html'
            )->attach(\Swift_Attachment::fromPath($filenName))
            /*
             * If you also want to include a plaintext version of the message
            ->addPart(
                $this->renderView(
                    'Emails/registration.txt.twig',
                    array('name' => $name)
                ),
                'text/plain'
            )
            */
        ;
        $this->mailer->send($message);
    }
    
    public function sendBookingActivityEmail($email, $reservatio_id, $data)
    {
        $fs = new Filesystem();

        $filePath = $this->container->getParameter('kernel.root_dir') . '/../web/'.$reservatio_id.'.pdf';
        $fs->rename($filePath, 'voucher-vs.pdf');
        $filenName = $this->container->getParameter('kernel.root_dir') . '/../web/voucher-vs.pdf';

        $message = \Swift_Message::newInstance()
            ->setSubject('Viento Sur Operadores Turísticos - Solicitud de compra de Actividades - Número: '.$reservatio_id)
//            ->setFrom('info@vientosur.net','VientoSur.net')
            ->setFrom("no-replay@vientosur.net", 'Viento Sur Operadores Turísticos')
            ->setTo([$email])
            ->setBody(
                $this->templating->render(
                    '@VientoSurAppApp/layoutEmailActivityPdf.html.twig',
                    $data
                ),
                'text/html'
            )->attach(\Swift_Attachment::fromPath($filenName))
            /*
             * If you also want to include a plaintext version of the message
            ->addPart(
                $this->renderView(
                    'Emails/registration.txt.twig',
                    array('name' => $name)
                ),
                'text/plain'
            )
            */
        ;
        $this->mailer->send($message);
    }

    public function sendCancellationEmail($email, $data)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Cancelación de reserva')
            ->setFrom("no-replay@vientosur.net", 'vientosur.net')
//            ->setFrom('info@vientosur.net','VientoSur.net')
            ->setTo($email)
            ->setBody(
                $this->templating->render(
                    'VientoSurAppAppBundle:Email:HotelCancellation.html.twig',
                    $data
                ),
                'text/html'
            )/*
             * If you also want to include a plaintext version of the message
            ->addPart(
                $this->renderView(
                    'Emails/registration.txt.twig',
                    array('name' => $name)
                ),
                'text/plain'
            )
            */
        ;
        $this->mailer->send($message);
    }

    public function sendBookingFlightEmail($email, $data)
    {
        $filenName = $this->container->getParameter('kernel.root_dir') . '/../web/reservation-vs.pdf';

        $message = \Swift_Message::newInstance()
            ->setSubject('Viento Sur Operadores Turísticos - Solicitud de compra de vuelo - Número: '.$data['reservation']->getReservationId())
            ->setFrom("no-replay@vientosur.net", 'Viento Sur Operadores Turísticos')
            ->setTo($email)
            ->setBody(
                $this->templating->render(
                    '@VientoSurAppApp/layoutEmailFligthPdf.html.twig',
                    $data
                ),
                'text/html'
            )->attach(\Swift_Attachment::fromPath($filenName));
        $this->mailer->send($message);
    }

    public function sendBookingEmailApi($email, $data)
    {
        $reservationId = $this->session->get('despegarReservationId');
        $internalId = $this->session->get('reservationInternalId');

        $filenName = $this->container->getParameter('kernel.root_dir') . '/../web/uploads/'.$internalId.'/voucher-vs.pdf';
//        $fs->rename($filePath, 'voucher-vs.pdf');
//        $filenName = $this->container->getParameter('kernel.root_dir') . '/../web/voucher-vs.pdf';

        $message = \Swift_Message::newInstance()
            ->setSubject('Viento Sur Operadores Turísticos - Solicitud de compra de Hotel - Número: '.$reservationId)
//            ->setFrom('info@vientosur.net','VientoSur.net')
            ->setFrom("no-replay@vientosur.net", 'Viento Sur Operadores Turísticos')
            ->setTo([$email])
            ->setBody(
                $this->templating->render(
                    '@VientoSurAppApp/layoutEmailHotelPdf.html.twig',
                    $data
                ),
                'text/html'
            )->attach(\Swift_Attachment::fromPath($filenName));
        $this->mailer->send($message);
    }
}