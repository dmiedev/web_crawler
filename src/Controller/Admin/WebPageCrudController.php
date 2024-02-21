<?php

namespace App\Controller\Admin;

use App\Entity\ExecutionStatus;
use App\Entity\WebPage;
use App\Message\ExecuteWebPageMessage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class WebPageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return WebPage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnIndex(),
            TextField::new('label'),
            UrlField::new('url')
                ->hideOnIndex(),
            TextField::new('regexp')
                ->hideOnIndex(),
            TimeField::new('periodicity')
                ->setFormat("'Every' h 'hours and' m 'minutes'"),
            ArrayField::new('tags'),
            BooleanField::new('active'),
            DateTimeField::new('lastExecutionTime')
                ->hideOnForm(),
            ChoiceField::new('lastExecutionStatus')
                ->setChoices(ExecutionStatus::cases())
                ->hideOnForm(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('url')
            ->add('label')
            ->add('tags');
    }

    public function configureActions(Actions $actions): Actions
    {
        $executeAction = Action::new('Execute')
            ->linkToCrudAction('executeWebPage');

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $executeAction);
    }

    public function executeWebPage(AdminContext $context, MessageBusInterface $messageBus, AdminUrlGenerator $urlGenerator): Response
    {
        /** @var WebPage $webPage */
        $webPage = $context->getEntity()->getInstance();
        $messageBus->dispatch(new ExecuteWebPageMessage($webPage->getId(), true));

        $url = $urlGenerator
            ->setController(WebPageCrudController::class)
            ->setAction(Crud::PAGE_INDEX)
            ->generateUrl();
        return $this->redirect($url);
    }
}
