<?php

	namespace AppBundle\Controller;

	use AppBundle\DBAL\Types\TransactionStatusType;
	use AppBundle\Entity\Message;
	use AppBundle\Entity\Transaction;
	use AppBundle\Entity\User;
	use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\Response;

	/**
	 * Message-related tasks
	 *
	 * @Route("/message")
	 */
	class MessageController extends Controller {
		/**
		 * {@inheritdoc}
		 */
		protected function getById($class, $id, $enabled_only = true) {
			$user = $this->getAuthenticatedUser();
			$entity = parent::getById($class, $id);
			if (
				$user !== $entity->getAuthor() &&
				$user !== $entity->getDest()
			) {
				throw $this->createAccessDeniedException(
					'You are not allowed to see the '.$class.' with id '.$id
				);
			}

			return $entity;
		}

		/**
		 * Generate a form allowing creation or edition of a Message
		 *
		 * @Route("/edit/{id}")
		 *
		 * @param Request     $request
		 * @param User        $author
		 * @param User        $dest
		 * @param Transaction $transaction
		 *
		 * @return Response
		 */
		protected function generateForm(
			Request $request,
			User $author,
			User $dest, 
			Transaction $transaction
		) {
			$message = new Message();
			$message
				->setAuthor($author)
				->setDest($dest)
				->setTransaction($transaction);

			$form = $this
				->get('form.factory')
				->createNamed(
					'new_message',
					'AppBundle\Form\MessageType',
					$message
				);
			$form->handleRequest($request);

			if ($form->isSubmitted() && $form->isValid()) {

				$message = $form
					->getData()
					->setDate(new \DateTime());

				// If the duration is not a valid value, set it to 0
				$duration = (float) $message->getDuration();
				if ($duration < 0) {
					$duration = 0;
					$message->setDuration($duration);
				}

				$em = $this->getDoctrine()->getManager();
				$em->persist($message);

				// Validate the Transaction if asked to do so
				if ($message->getValidation()) {
					$message->getTransaction()->validate();
				}
				// Do not change the Transaction duration if it should be validated
				else {
					// If the duration is not 0, store it in the Transaction
					if ($duration != 0) {
						$transaction->setDuration($duration);
					}
				}

				$em->flush();

				// send an e-mail to the Message recipient
				$email = \Swift_Message::newInstance()
					->setSubject('La Bouée Corsaire - nouveau message')
					->setFrom($this->getParameter('postmaster.email'))
					->setTo($dest->getEmail())
					->setBody(
						$this->renderView(
							'message/email.txt.twig',
							[
								'dest'    => $dest,
								'author'  => $author,
								'message' => $message,
							]
						),
						'text/plain'
					);
				$this->get('mailer')->send($email);

				return $this->redirectToRoute('message_show', [
					'id' => $message->getId()
				]);
			}

			return $this->render('message/new.html.twig', [
				'form' => $form->createView(),
				'message' => $message,
			]);

		}

		/**
		 * Show a form allowing creation of a new Message associated to the Task identified by the given ID
		 *
		 * @Route("/new/{id}", name="message_new")
		 *
		 * @param int $id ID of the associated Task
		 *
		 * @return Response
		 */
		public function newAction(Request $request, $id) {
			$author = $this->getAuthenticatedUser();

			$task = $this
				->getDoctrine()
				->getRepository('AppBundle:Task')
				->find($id);

			$dest = $task->getUser();

			// find all Transactions associated with current Task
			$transactions = $this
				->getDoctrine()
				->getRepository('AppBundle:Transaction')
				->findBy([
					'task' => $task,
				]);

			// find Transaction associated with current User (from previous list)
			$found = false;
			if (count($transactions) > 0) {
				foreach ($transactions as $transaction) {
					if ($transaction->getUsers()->contains($author)) {
						$found = true;
						break;
					}
				}
			}

			// if no Transaction is associated with both the current Task and User,
			// create a new one
			if (! $found) {
				$transaction = new Transaction();
				$transaction
					->setTask($task)
					->addUser($author)
					->addUser($dest);
				$this->getDoctrine()->getManager()->persist($transaction);
			}

			return $this->generateForm($request, $author, $dest, $transaction);

		}

		/**
		 * Show a form allowing creation of a new Message in answer to an existing one
		 *
		 * @Route("/answer/{id}", name="message_answer")
		 *
		 * @param int $id ID of the existing Message
		 *
		 * @return Response
		 */
		public function answerAction(Request $request, $id) {
			$message = $this->getById('Message', $id);

			return $this->generateForm(
				$request,
				$this->getAuthenticatedUser(),
				$message->getAuthor(),
				$message->getTransaction()
			);
		}

		/**
		 * Show Message identified by the given ID
		 *
		 * @Route("/show/{id}", name="message_show")
		 *
		 * @param int $id
		 *
		 * @return Response
		 */
		public function showAction(Request $request, $id) {
			$user = $this->getAuthenticatedUser();
			$message = $this->getById('Message', $id);

			return $this->render('message/show.html.twig', [
				'message' => $message,
				'user' => $user,
			]);

		}

	}

?>
