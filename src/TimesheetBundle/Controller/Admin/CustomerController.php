<?php

/*
 * This file is part of the Kimai package.
 *
 * (c) Kevin Papst <kevin@kevinpapst.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TimesheetBundle\Controller\Admin;

use AppBundle\Controller\AbstractController;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Request;
use TimesheetBundle\Entity\Customer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use TimesheetBundle\Form\CustomerEditForm;
use TimesheetBundle\Form\CustomerToolbarForm;
use TimesheetBundle\Repository\Query\CustomerQuery;

/**
 * Controller used to manage activities in the admin part of the site.
 *
 * @Route("/admin/customer")
 * @Security("has_role('ROLE_ADMIN')")
 *
 * @author Kevin Papst <kevin@kevinpapst.de>
 */
class CustomerController extends AbstractController
{

    /**
     * @param Request $request
     * @return CustomerQuery
     */
    protected function getQueryForRequest(Request $request)
    {
        $visibility = $request->get('visibility');
        if (strlen($visibility) == 0 || (int)$visibility != $visibility) {
            $visibility = CustomerQuery::SHOW_BOTH;
        }
        $pageSize = (int) $request->get('pageSize');

        $query = new CustomerQuery();
        $query
            ->setPageSize($pageSize)
            ->setVisibility($visibility);

        return $query ;
    }

    /**
     * @Route("/", defaults={"page": 1}, name="admin_customer")
     * @Route("/page/{page}", requirements={"page": "[1-9]\d*"}, name="admin_customer_paginated")
     * @Method("GET")
     * @Cache(smaxage="10")
     */
    public function indexAction($page, Request $request)
    {
        $query = $this->getQueryForRequest($request);
        $query->setPage($page);

        /* @var $entries Pagerfanta */
        $entries = $this->getDoctrine()->getRepository(Customer::class)->findByQuery($query);

        return $this->render('TimesheetBundle:admin:customer.html.twig', [
            'entries' => $entries,
            'query' => $query,
            'toolbarForm' => $this->getToolbarForm($query)->createView(),
        ]);
    }

    /**
     * @Route("/create", name="admin_customer_create")
     * @Method({"GET", "POST"})
     */
    public function createAction(Request $request)
    {
        return $this->renderCustomerForm(new Customer(), $request);
    }

    /**
     * @Route("/{id}/edit", name="admin_customer_edit")
     * @Method({"GET", "POST"})
     * @Security("is_granted('edit', customer)")
     */
    public function editAction(Customer $customer, Request $request)
    {
        return $this->renderCustomerForm($customer, $request);
    }

    /**
     * @param Customer $customer
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    protected function renderCustomerForm(Customer $customer, Request $request)
    {
        $editForm = $this->createEditForm($customer);

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($customer);
            $entityManager->flush();

            $this->flashSuccess('action.updated_successfully');

            return $this->redirectToRoute('admin_customer', ['id' => $customer->getId()]);
        }

        return $this->render(
            'TimesheetBundle:admin:customer_edit.html.twig',
            [
                'customer' => $customer,
                'form' => $editForm->createView()
            ]
        );
    }

    /**
     * @param CustomerQuery $query
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function getToolbarForm(CustomerQuery $query)
    {
        return $this->createForm(
            CustomerToolbarForm::class,
            $query,
            [
                'action' => $this->generateUrl('admin_customer_paginated', [
                    'page' => $query->getPage(),
                ]),
                'method' => 'GET',
            ]
        );
    }

    /**
     * @param Customer $customer
     * @return \Symfony\Component\Form\FormInterface
     */
    private function createEditForm(Customer $customer)
    {
        if ($customer->getId() === null) {
            $url = $this->generateUrl('admin_customer_create');
        } else {
            $url = $this->generateUrl('admin_customer_edit', ['id' => $customer->getId()]);
        }

        return $this->createForm(
            CustomerEditForm::class,
            $customer,
            [
                'action' => $url,
                'method' => 'POST'
            ]
        );
    }
}