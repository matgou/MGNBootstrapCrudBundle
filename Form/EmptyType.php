<?php

namespace MGN\Bundle\BootstrapCrudBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class EmptyType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
    }

    public function getName()
    {
        return 'jkr_bundle_shoppinglistbundle_emptytype';
    }
}
