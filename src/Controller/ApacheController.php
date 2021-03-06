<?php

namespace App\Controller;

use App\Entity\Alias;
use App\Entity\VirtualHost;
use App\Form\AliasType;
use App\Form\VirtualHostType;
use App\Tools\PaginationTools;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class ApacheController
 *
 * @package App\Controller
 * @author Bruno Buiret <bruno.buiret@gmail.com>
 * @Route("/apache", name="apache_")
 */
class ApacheController extends AbstractController
{
    /**
     * Displays Apache's loaded modules.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/modules", methods={"GET"}, name="modules")
     */
    public function modules(): Response
    {
        // Initialize vars
        /** @noinspection PhpComposerExtensionStubsInspection */
        $availableModules = function_exists('apache_get_modules') ? apache_get_modules() : [];
        $modules = [];

        foreach($availableModules as $module)
        {
            $modules[$module] = sprintf(
                'https://httpd.apache.org/docs/current/%s/mod/%s.html',
                'en',
                $module
            );
        }

        return $this->render(
            'apache/modules.html.twig',
            [
                'modules' => $modules,
            ]
        );
    }

    // ---

    /**
     * Displays a list of virtual hosts.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/virtual-hosts", methods={"GET"}, name="virtual_hosts_list")
     */
    public function virtualHostsList(Request $request): Response
    {
        /** @var \App\Repository\VirtualHostRepository $repository */
        $repository = $this->getDoctrine()->getRepository(VirtualHost::class);
        $paginationTools = new PaginationTools(
            $request->query->getInt('page', 1),
            $repository->count([])
        );

        return $this->render(
            'apache/virtual-hosts/list.html.twig',
            [
                'virtualHosts' => $repository->findBy(
                    [],
                    ['name' => 'ASC'],
                    $paginationTools->getItemsPerPageNumber(),
                    $paginationTools->getOffset()
                ),
                'total'        => $paginationTools->getItemsNumber(),
                'currentPage'  => $paginationTools->getCurrentPage(),
                'pagesNumber'  => $paginationTools->getPagesNumber(),
            ]
        );
    }

    /**
     * Displays and handles a form to add a new virtual host.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \Symfony\Contracts\Translation\TranslatorInterface $translator
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/virtual-hosts/add", methods={"GET", "POST"}, name="virtual_hosts_add")
     */
    public function addVirtualHost(Request $request, TranslatorInterface $translator): Response
    {
        // Initialize form
        $virtualHost = new VirtualHost();
        $form = $this->createForm(
            VirtualHostType::class,
            $virtualHost,
            [
                'method'             => 'post',
                'action'             => $this->generateUrl('apache_virtual_hosts_add'),
                'translation_domain' => 'apache',
            ]
        );
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($virtualHost);
            $entityManager->flush();

            $this->addFlash('success', 'Le nouvel hôte virtuel "'.$virtualHost->getName().'" a été créé.');

            return $this->redirectToRoute('apache_virtual_hosts_list');
        }

        return $this->render(
            'apache/virtual-hosts/form.html.twig',
            [
                'form'   => $form->createView(),
                'title'  => $translator->trans('virtual_hosts.add.title', [], 'apache'),
                'action' => $translator->trans('virtual_hosts.add.button_add', [], 'apache'),
                'reset'  => $translator->trans('virtual_hosts.add.button_reset', [], 'apache'),
            ]
        );
    }

    /**
     * Displays and handles a form to edit an existing virtual host.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $id The virtual host's id.
     * @param \Symfony\Contracts\Translation\TranslatorInterface $translator
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/virtual-hosts/{id<\d+>}/edit", methods={"GET", "POST"}, name="virtual_hosts_edit")
     */
    public function editVirtualHost(Request $request, int $id, TranslatorInterface $translator): Response
    {
        // Initialize vars
        /** @var \App\Repository\VirtualHostRepository $repository */
        $repository = $this->getDoctrine()->getRepository(VirtualHost::class);
        /** @var \App\Entity\VirtualHost $virtualHost */
        $virtualHost = $repository->findOneBy(['id' => $id]);

        if(null === $virtualHost)
        {
            throw $this->createNotFoundException(sprintf(
                'No virtual host found for id #%u.',
                $id
            ));
        }

        // Initialize form
        $form = $this->createForm(
            VirtualHostType::class,
            $virtualHost,
            [
                'method'             => 'post',
                'action'             => $this->generateUrl('apache_virtual_hosts_edit', ['id' => $id]),
                'translation_domain' => 'apache',
            ]
        );
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($virtualHost);
            $entityManager->flush();

            $this->addFlash('success', 'L\'hôte virtuel "'.$virtualHost->getName().'" a été édité.');

            return $this->redirectToRoute('apache_virtual_hosts_list');
        }

        return $this->render(
            'apache/virtual-hosts/form.html.twig',
            [
                'form'   => $form->createView(),
                'title'  => $translator->trans('virtual_hosts.edit.title', [], 'apache'),
                'action' => $translator->trans('virtual_hosts.edit.button_add', [], 'apache'),
                'reset'  => $translator->trans('virtual_hosts.edit.button_reset', [], 'apache'),
            ]
        );
    }

    /**
     * Deletes an existing virtual host.
     *
     * @param int $id The virtual host's id.
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/virtual-hosts/{id<\d+>}/delete", methods={"GET"}, name="virtual_hosts_delete")
     */
    public function deleteVirtualHost(int $id): Response
    {
        // Initialize vars
        /** @var \App\Repository\VirtualHostRepository $repository */
        $repository = $this->getDoctrine()->getRepository(VirtualHost::class);
        /** @var \App\Entity\VirtualHost $virtualHost */
        $virtualHost = $repository->findOneBy(['id' => $id]);

        if(null === $virtualHost)
        {
            throw $this->createNotFoundException(sprintf(
                'No virtual host found for id #%u.',
                $id
            ));
        }

        $manager = $this->getDoctrine()->getManager();
        $manager->remove($virtualHost);
        $manager->flush();

        $this->addFlash('success', 'L\'hôte virtuel "'.$virtualHost->getName().'" a été supprimé.');

        return $this->redirectToRoute('apache_virtual_hosts_list');
    }

    /**
     * Toggles an existing virtual host's hidden status.
     *
     * @param int $id The virtual host's id.
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/virtual-hosts/{id<\d+>}/toggle-hidden", methods={"POST"}, name="virtual_hosts_toggle_hidden")
     */
    public function toggleVirtualHostHidden(int $id): Response
    {
        // Initialize vars
        /** @var \App\Repository\VirtualHostRepository $repository */
        $repository = $this->getDoctrine()->getRepository(VirtualHost::class);
        /** @var \App\Entity\VirtualHost $virtualHost */
        $virtualHost = $repository->findOneBy(['id' => $id]);

        if(null === $virtualHost)
        {
            throw $this->createNotFoundException(sprintf(
                'No virtual host found for id #%u.',
                $id
            ));
        }

        // Toggle hidden status
        $virtualHost->setHidden(!$virtualHost->isHidden());

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($virtualHost);
        $entityManager->flush();

        return $this->json(['hidden' => $virtualHost->isHidden()]);
    }

    // ---

    /**
     * Displays a list of aliases.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/aliases", methods={"GET"}, name="aliases_list")
     */
    public function aliasesList(Request $request): Response
    {
        /** @var \App\Repository\AliasRepository $repository */
        $repository = $this->getDoctrine()->getRepository(Alias::class);
        $paginationTools = new PaginationTools(
            $request->query->getInt('page', 1),
            $repository->count([])
        );

        return $this->render(
            'apache/aliases/list.html.twig',
            [
                'aliases'     => $repository->findBy(
                    [],
                    ['name' => 'ASC'],
                    $paginationTools->getItemsPerPageNumber(),
                    $paginationTools->getOffset()
                ),
                'total'       => $paginationTools->getItemsNumber(),
                'currentPage' => $paginationTools->getCurrentPage(),
                'pagesNumber' => $paginationTools->getPagesNumber(),
            ]
        );
    }

    /**
     * Displays and handles a form to add a new alias.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \Symfony\Contracts\Translation\TranslatorInterface $translator
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/aliases/add", methods={"GET", "POST"}, name="aliases_add")
     */
    public function addAlias(Request $request, TranslatorInterface $translator): Response
    {
        // Initialize form
        $alias = new Alias();
        $form = $this->createForm(
            AliasType::class,
            $alias,
            [
                'method'             => 'post',
                'action'             => $this->generateUrl('apache_aliases_add'),
                'translation_domain' => 'apache',
            ]
        );
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($alias);
            $entityManager->flush();

            $this->addFlash('success', 'Le nouvel alias "'.$alias->getName().'" a été créé.');

            return $this->redirectToRoute('apache_aliases_list');
        }

        return $this->render(
            'apache/aliases/form.html.twig',
            [
                'form'   => $form->createView(),
                'title'  => $translator->trans('aliases.add.title', [], 'apache'),
                'action' => $translator->trans('aliases.add.button_add', [], 'apache'),
                'reset'  => $translator->trans('aliases.add.button_reset', [], 'apache'),
            ]
        );
    }

    /**
     * Displays and handles a form to edit an existing alias.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $id The alias' id.
     * @param \Symfony\Contracts\Translation\TranslatorInterface $translator
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/aliases/{id<\d+>}/edit", methods={"GET", "POST"}, name="aliases_edit")
     */
    public function editAlias(Request $request, int $id, TranslatorInterface $translator): Response
    {
        // Initialize vars
        /** @var \App\Repository\AliasRepository $repository */
        $repository = $this->getDoctrine()->getRepository(Alias::class);
        /** @var \App\Entity\Alias $alias */
        $alias = $repository->findOneBy(['id' => $id]);

        if(null === $alias)
        {
            throw $this->createNotFoundException(sprintf(
                'No alias found for id #%u.',
                $id
            ));
        }

        // Initialize form
        $form = $this->createForm(
            AliasType::class,
            $alias,
            [
                'method'             => 'post',
                'action'             => $this->generateUrl('apache_aliases_edit', ['id' => $id]),
                'translation_domain' => 'apache',
            ]
        );
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($alias);
            $entityManager->flush();

            $this->addFlash('success', 'L\'alias "'.$alias->getName().'" a été édité.');

            return $this->redirectToRoute('apache_aliases_list');
        }

        return $this->render(
            'apache/aliases/form.html.twig',
            [
                'form'   => $form->createView(),
                'title'  => $translator->trans('aliases.edit.title', [], 'apache'),
                'action' => $translator->trans('aliases.edit.button_add', [], 'apache'),
                'reset'  => $translator->trans('aliases.edit.button_reset', [], 'apache'),
            ]
        );
    }

    /**
     * Deletes an existing alias.
     *
     * @param int $id The alias' id.
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/aliases/{id<\d+>}/delete", methods={"GET"}, name="aliases_delete")
     */
    public function deleteAlias(int $id): Response
    {
        // Initialize vars
        /** @var \App\Repository\AliasRepository $repository */
        $repository = $this->getDoctrine()->getRepository(Alias::class);
        /** @var \App\Entity\Alias $alias */
        $alias = $repository->findOneBy(['id' => $id]);

        if(null === $alias)
        {
            throw $this->createNotFoundException(sprintf(
                'No alias found for id #%u.',
                $id
            ));
        }

        $manager = $this->getDoctrine()->getManager();
        $manager->remove($alias);
        $manager->flush();

        $this->addFlash('success', 'L\'alias "'.$alias->getName().'" a été supprimé.');

        return $this->redirectToRoute('apache_aliases_list');
    }

    /**
     * Toggles an existing alias'shidden status.
     *
     * @param int $id The alias' id.
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/aliases/{id<\d+>}/toggle-hidden", methods={"POST"}, name="aliases_toggle_hidden")
     */
    public function toggleAliasHidden(int $id): Response
    {
        // Initialize vars
        /** @var \App\Repository\AliasRepository $repository */
        $repository = $this->getDoctrine()->getRepository(Alias::class);
        /** @var \App\Entity\Alias $alias */
        $alias = $repository->findOneBy(['id' => $id]);

        if(null === $alias)
        {
            throw $this->createNotFoundException(sprintf(
                'No alias found for id #%u.',
                $id
            ));
        }

        // Toggle hidden status
        $alias->setHidden(!$alias->isHidden());

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($alias);
        $entityManager->flush();

        return $this->json(['hidden' => $alias->isHidden()]);
    }
}