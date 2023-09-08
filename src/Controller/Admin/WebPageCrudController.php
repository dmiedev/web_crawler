<?php

namespace App\Controller\Admin;

use App\Entity\ExecutionStatus;
use App\Entity\WebPage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class WebPageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return WebPage::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
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
}
