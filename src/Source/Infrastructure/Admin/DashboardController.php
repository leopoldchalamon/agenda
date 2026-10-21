<?php

declare(strict_types=1);

namespace App\Source\Infrastructure\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->redirectToRoute('admin_source_index');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Agenda');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkTo(SourceCrudController::class, 'Sources', 'fa fa-rss');
    }
}
