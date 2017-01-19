<?php

	namespace AppBundle\Controller;

	use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
	use Symfony\Bundle\FrameworkBundle\Controller\Controller;

	/**
	 * Administration tasks
	 *
	 * @Route("/admin")
	 */
	class AdminController extends Controller {

		/**
		 * Renders summary of administration tasks
		 *
		 * @Route("/", name="admin_home")
		 */
		public function indexAction() {
			return $this->render('admin/index.html.twig');
		}

	}

?>
