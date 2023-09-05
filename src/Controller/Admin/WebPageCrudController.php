<?php

namespace App\Controller\Admin;

use App\Entity\WebPage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
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
            TimeField::new('periodicity'),
            ArrayField::new('tags'),
            BooleanField::new('active'),
            DateTimeField::new('lastExecutionTime')
                ->hideOnForm(),
            TextField::new('lastExecutionStatus')
                ->hideOnForm(),
        ];
    }
}
