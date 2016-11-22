<?php

namespace AwesomeBundle\Controller;

use AwesomeBundle\Entity\Message;
use AwesomeBundle\Entity\Ticket;
use AwesomeBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

/**
 * Ticket controller.
 *
 * @Route("ticket")
 */
class TicketController extends Controller
{
    /**
     * Lists all ticket entities.
     *
     * @Route("/", name="ticket_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();
        $userConnected = $this->get('security.token_storage')->getToken()->getUser();

        if ($userConnected !== 'anon.') {

            $allTickets = $em->getRepository('AwesomeBundle:Ticket')
                ->findBy([], array('created' => 'DESC'));

            $userTickets = $em->getRepository('AwesomeBundle:Ticket')
                ->findBy(['owner' => $userConnected], array('created' => 'DESC'));

            $ticketsGranted = $userConnected->getTickets();

            if ($userConnected->hasRole('ROLE_ADMIN')) {
                return $this->render('ticket/index.html.twig', array(
                    'tickets' => $allTickets,
                ));
            }
            return $this->render('ticket/index.html.twig', array(
                'tickets_created' => $userTickets,
                'tickets_granted' => $ticketsGranted
            ));
        } else {
            return $this->render('ticket/index.html.twig', array());
        }
    }

    /**
     * Creates a new ticket entity.
     *
     * @Route("/new", name="ticket_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
        if (!$user) {
            throw new \Exception('You have to be logged in');
        }

        $ticket = new Ticket();

        $form = $this->createForm('AwesomeBundle\Form\TicketType', $ticket);
        $form->handleRequest($request);

        $message = new Message();
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $ticket->setCreated(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
            $ticket->setUpdated(new \DateTime('now', new \DateTimeZone('Europe/Paris')));
            $ticket->setOwner($user);

            $message->setContent($request->request->get('message'));
            $message->setTicket($ticket);
            $message->setCreated(new \DateTime('now'));
            $message->setUpdated(new \DateTime('now'));
            $message->setUser($user);

            $em->persist($message);
            $em->flush($message);

            $em->persist($ticket);
            $em->flush($ticket);

            return $this->redirectToRoute('ticket_show', array('id' => $ticket->getId()));
        }

        return $this->render('ticket/new.html.twig', array(
            'ticket' => $ticket,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a ticket entity.
     *
     * @Route("/{id}", name="ticket_show")
     * @Method("GET")
     */
    public function showAction(Ticket $ticket, Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();

        if (!$user->getId()) {
            throw new \Exception('You have no permission');
        }
        if (!$user->hasRole('ROLE_ADMIN')) {
            if ($user != $ticket->getOwner() && !in_array($user, $ticket->getUsers()->getValues())) {
                throw new \Exception('You have no permission');
            }
        }

        $deleteForm = $this->createDeleteForm($ticket);

        $message = new Message();
        $form = $this->createForm('AwesomeBundle\Form\MessageType', $message);


        $messages = $this->getDoctrine()
            ->getRepository('AwesomeBundle:Message')
            ->findBy(['ticket' => $ticket], array('id' => 'ASC'));

        return $this->render('ticket/show.html.twig', array(
            'messages' => $messages,
            'ticket' => $ticket,
            'delete_form' => $deleteForm->createView(),
            'form' => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing ticket entity.
     *
     * @Route("/{id}/edit", name="ticket_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Ticket $ticket)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();

        if (!$user->hasRole('ROLE_ADMIN')) {
            throw new \Exception('You have no permission');

        }

        $deleteForm = $this->createDeleteForm($ticket);

        $editForm = $this->createForm('AwesomeBundle\Form\TicketEditType', $ticket);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('ticket_show', array('id' => $ticket->getId()));
        }

        return $this->render('ticket/edit.html.twig', array(
            'ticket' => $ticket,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a ticket entity.
     *
     * @Route("/{id}", name="ticket_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Ticket $ticket)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();

        if (!$user->hasRole('ROLE_ADMIN')) {
            throw new \Exception('You have no permission');

        }

        $form = $this->createDeleteForm($ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $messages = $em
                ->getRepository('AwesomeBundle:Message')
                ->findBy(['ticket' => $ticket], array('id' => 'DESC'));
            foreach ($messages as $message) {
                $em->remove($message);
                $em->flush($message);
            }
            $em->remove($ticket);
            $em->flush($ticket);
        }

        return $this->redirectToRoute('ticket_index');
    }

    /**
     * Creates a form to delete a ticket entity.
     *
     * @param Ticket $ticket The ticket entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Ticket $ticket)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('ticket_delete', array('id' => $ticket->getId())))
            ->setMethod('DELETE')
            ->getForm();
    }
}
