<?php

	namespace AppBundle\Controller;

	use AppBundle\Entity\User;
	use FOS\UserBundle\Event\GetResponseUserEvent;
	use FOS\UserBundle\Event\FilterUserResponseEvent;
	use FOS\UserBundle\Event\FormEvent;
	use FOS\UserBundle\FOSUserEvents;
	use FOS\UserBundle\Model\UserManagerInterface;
	use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
	use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
	use Symfony\Component\EventDispatcher\EventDispatcherInterface;
	use Symfony\Component\Form\FormInterface;
	use Symfony\Component\HttpFoundation\RedirectResponse;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\Security\Core\Exception\AuthenticationException;
	use Symfony\Component\Security\Core\Security;

	/**
	 * Users creation and connection
	 */
	class SecurityController extends BaseController {

		/**
		 * Show forms allowing to connect an existing User or create a new one
		 *
		 * @Route("/login", name="user_login")
		 *
		 * @param Request $request
		 *
		 * @return Response
		 */
		public function loginAction(Request $request) {
			/** @var $formFactory FactoryInterface */
			$formFactory = $this->get('fos_user.registration.form.factory');
			/** @var $userManager UserManagerInterface */
			$userManager = $this->get('fos_user.user_manager');
			/** @var $dispatcher EventDispatcherInterface */
			$dispatcher = $this->get('event_dispatcher');

			$user = $userManager->createUser();
			$user->setEnabled(true);

			$event = new GetResponseUserEvent($user, $request);
			$dispatcher->dispatch(
				FOSUserEvents::REGISTRATION_INITIALIZE,
				$event
			);

			if (null !== $event->getResponse()) {
				return $event->getResponse();
			}

			$form = $formFactory->createForm();
			$form->setData($user);

			$form->handleRequest($request);

			if ($form->isSubmitted()) {
				$response = $this->newUser(
					$form,
					$request,
					$dispatcher,
					$userManager,
					$user
				);
				if ($response !== null) {
					return $response;
				}
			}

			/** @var $session \Symfony\Component\HttpFoundation\Session\Session */
			$session = $request->getSession();

			$authErrorKey = Security::AUTHENTICATION_ERROR;
			$lastUsernameKey = Security::LAST_USERNAME;

			// get the error if any (works with forward and redirect -- see below)
			if ($request->attributes->has($authErrorKey)) {
				$error = $request->attributes->get($authErrorKey);
			} elseif (null !== $session && $session->has($authErrorKey)) {
				$error = $session->get($authErrorKey);
				$session->remove($authErrorKey);
			} else {
				$error = null;
			}

			if (!$error instanceof AuthenticationException) {
				$error = null; // The value does not come from the security component.
			}

			// last username entered by the user
			$lastUsername = (null === $session)
				? ''
				: $session->get($lastUsernameKey);

			$csrfToken = $this->has('security.csrf.token_manager')
				? $this
					->get('security.csrf.token_manager')
					->getToken('authenticate')
					->getValue()
				: null;

			return $this->render('user/login.html.twig', [
				'form' => $form->createView(),
				'last_username' => $lastUsername,
				'error' => $error,
				'csrf_token' => $csrfToken,
			]);
		}

		/**
		 * Create a new User
		 *
		 * @param FormInterface            $form
		 * @param Request                  $request
		 * @param EventDispatcherInterface $dispatcher
		 * @param UserManagerInterface     $userManager
		 * @param User                     $user
		 *
		 * @return Response
		 */
		public function newUser(
			FormInterface $form,
			Request $request,
			EventDispatcherInterface $dispatcher,
			UserManagerInterface $userManager,
			User $user
		) {
			if ($form->isValid()) {
				$event = new FormEvent($form, $request);
				$dispatcher->dispatch(
					FOSUserEvents::REGISTRATION_SUCCESS,
					$event
				);

				$userManager->updateUser($user);

				// If this is the first User created, give it administration rights
				if ($user->getId() == 1) {
					$user->setSuperAdmin(true);
					$userManager->updateUser($user);
				}

				$response = $event->getResponse();
				if ($response === null) {
					$url = $this->generateUrl('fos_user_registration_confirmed');
					$response = new RedirectResponse($url);
				}

				$dispatcher->dispatch(
					FOSUserEvents::REGISTRATION_COMPLETED,
					new FilterUserResponseEvent($user, $request, $response)
				);

				return $response;
			}

			$event = new FormEvent($form, $request);
			$dispatcher->dispatch(
				FOSUserEvents::REGISTRATION_FAILURE,
				$event
			);

			return $event->getResponse();
		}

	}

?>
