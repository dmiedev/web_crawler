<?php

namespace App\Controller\Admin;

use App\Entity\Execution;
use App\Entity\WebPage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {}

    #[Route('/', name: 'admin')]
    public function index(): Response
    {
        $url = $this->adminUrlGenerator->setController(WebPageCrudController::class)->generateUrl();
        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Web Crawler');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToCrud('Web Pages', 'fas fa-globe', WebPage::class);
        yield MenuItem::linkToCrud('Executions', 'fas fa-gears', Execution::class);
        yield MenuItem::linkToRoute('Graph', 'fas fa-diagram-project', 'app_admin_graph_index');
    }
}
