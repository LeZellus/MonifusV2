<?php

namespace App\Form;

use App\Entity\LotUnit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LotUnitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('actualSellPrice', IntegerType::class, [
                'label' => 'Prix de vente réel (kamas)',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes sur la vente',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500',
                    'rows' => 3
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LotUnit::class,
        ]);
    }
}