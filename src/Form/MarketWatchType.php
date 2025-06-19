<?php

namespace App\Form;

use App\Entity\MarketWatch;
use App\Entity\Item;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class MarketWatchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('item', EntityType::class, [
                'class' => Item::class,
                'choice_label' => 'name',
                'label' => 'Ressource observée',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('observedAt', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Date d\'observation',
                'data' => new \DateTimeImmutable(),
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('pricePerUnit', IntegerType::class, [
                'label' => 'Prix à l\'unité (x1)',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500',
                    'placeholder' => 'ex: 1500'
                ]
            ])
            ->add('pricePer10', IntegerType::class, [
                'label' => 'Prix par 10 (x10)',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500',
                    'placeholder' => 'ex: 14500'
                ]
            ])
            ->add('pricePer100', IntegerType::class, [
                'label' => 'Prix par 100 (x100)',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500',
                    'placeholder' => 'ex: 140000'
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes sur le marché',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500',
                    'rows' => 3,
                    'placeholder' => 'ex: Prix en hausse, forte demande, peu d\'offres...'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MarketWatch::class,
            'constraints' => [
                new Callback([$this, 'validateAtLeastOnePrice'])
            ]
        ]);
    }

    /**
     * Validation custom : au moins un prix doit être renseigné
     */
    public function validateAtLeastOnePrice(MarketWatch $marketWatch, ExecutionContextInterface $context): void
    {
        if (!$marketWatch->hasAnyPrice()) {
            $context->buildViolation('Vous devez renseigner au moins un prix (x1, x10 ou x100)')
                ->atPath('pricePerUnit')
                ->addViolation();
        }
    }
}