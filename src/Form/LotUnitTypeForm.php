<?php

namespace App\Form;

use App\Entity\LotGroup;
use App\Entity\LotUnit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LotUnitTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('soldAt')
            ->add('actualSellPrice')
            ->add('notes')
            ->add('lotGroup', EntityType::class, [
                'class' => LotGroup::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LotUnit::class,
        ]);
    }
}
