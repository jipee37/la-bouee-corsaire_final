<?php

	namespace AppBundle\Controller;

	use AppBundle\DBAL\Types\TransactionStatusType;
	use AppBundle\Entity\Transaction;
	use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\HttpFoundation\Response;

	/**
	 * Transaction-related operations
	 *
	 * @Route("/transaction")
	 */
	class TransactionController extends Controller {

		/**
		 * Show list of Transactions the current User is a part of
		 *
		 * @Route("/list", name="transaction_list")
		 *
		 * @return Response
		 */
		public function listAction() {
			$list = $this
				->getDoctrine()
				->getRepository('AppBundle:Transaction')
				->findBy(
					[],
					['id' => 'DESC']
				);

			$list_open = [];
			$list_validated = [];
			$list_done = [];
			foreach ($list as $transaction) {
				if ($transaction->getUsers()->contains($this->getUser())) {
					switch ($transaction->getStatus()) {

						case TransactionStatusType::OPEN:
						$list_open[] = $transaction;
						break;

						case TransactionStatusType::VALIDATED:
						$list_validated[] = $transaction;
						break;

						case TransactionStatusType::DONE:
						$list_done[] = $transaction;
						break;

					}
				}
			}

			return $this->render('transaction/list.html.twig', [
				'list_open'      => $list_open,
				'list_validated' => $list_validated,
				'list_done'      => $list_done,
			]);

		}

		/**
		 * Show details of the Transaction identified by the given ID
		 *
		 * @Route("/show/{id}", name="transaction_show")
		 *
		 * @param Request $request
		 * @param int     $id
		 *
		 * @return Response
		 */
		public function showAction(Request $request, $id) {
			$transaction = $this->getById('Transaction', $id);

			return $this->render('transaction/show.html.twig', [
				'transaction' => $transaction,
			]);

		}

	}

?>