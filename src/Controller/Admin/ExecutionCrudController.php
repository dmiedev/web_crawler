<?php

namespace App\Controller\Admin;

use App\Entity\Execution;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ExecutionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Execution::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('webPage'),
            ChoiceField::new('status'),
            DateTimeField::new('startTime'),
            DateTimeField::new('endTime'),
            IntegerField::new('crawledCount'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
         // return $actions->disable(Action::NEW);
        return $actions;
    }
}
