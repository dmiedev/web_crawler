<?php

namespace App\Controller\Admin;

use App\Entity\Execution;
use App\Entity\ExecutionStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class ExecutionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Execution::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setDefaultSort(['startTime' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            AssociationField::new('webPage'),
            ChoiceField::new('status')
                ->setChoices(ExecutionStatus::cases()),
            DateTimeField::new('startTime'),
            DateTimeField::new('endTime'),
            IntegerField::new('crawledCount'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
         return $actions->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('webPage');
    }
}
