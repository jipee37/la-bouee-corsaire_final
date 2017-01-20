<?php

	namespace AppBundle\Controller;

	use FOS\UserBundle\Event\FilterUserResponseEvent;
	use FOS\UserBundle\Event\FormEvent;
	use FOS\UserBundle\Event\GetResponseUserEvent;
	use FOS\UserBundle\FOSUserEvents;
	use FOS\UserBundle\Controller\ProfileController as BaseController;
	use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
	use Symfony\Component\EventDispatcher\EventDispatcherInterface;
	use Symfony\Component\HttpFoundation\RedirectResponse;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\Response;
	use Symfony\Component\Security\Core\Exception\AccessDeniedException;

	/**
	 * User profile edition
	 */
	class ProfileController extends BaseController {

		/**
		 * Show a form allowing to edit the current User
		 *
		 * @Route("/profile/edit", name="user_profile_edit")
		 *
		 * @param Request $request
		 *
		 * @return Response
		 */
		public function editAction(Request $request) {
			$user = $this->getUser();
			if (!$user) {
				throw new AccessDeniedException();
			}

			/** @var $dispatcher EventDispatcherInterface */
			$dispatcher = $this->get('event_dispatcher');

			$event = new GetResponseUserEvent($user, $request);
			$dispatcher->dispatch(
				FOSUserEvents::PROFILE_EDIT_INITIALIZE,
				$event
			);

			$response = $event->getResponse();
			if ($response !== null) {
				return $response;
			}

			$form = $this
				->get('fos_user.profile.form.factory')
				->createForm();
			$form->setData($user);
			$form->handleRequest($request);

			if ($form->isSubmitted() && $form->isValid()) {

				$event = new FormEvent($form, $request);
				$dispatcher->dispatch(
					FOSUserEvents::PROFILE_EDIT_SUCCESS,
					$event
				);

				$this
					->get('fos_user.user_manager')
					->updateUser($user);

				$response = $event->getResponse();
				if ($response === null) {
					$response = new RedirectResponse(
						$this->generateUrl('fos_user_profile_show')
					);
				}

				$dispatcher->dispatch(
					FOSUserEvents::PROFILE_EDIT_COMPLETED,
					new FilterUserResponseEvent($user, $request, $response)
				);

				return $response;
			}

			return $this->render('user/edit.html.twig', [
				'form' => $form->createView(),
				'user' => $user,
			]);
		}

	}

?>
