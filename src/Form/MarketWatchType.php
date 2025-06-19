<?php

namespace App\Form;

use App\Entity\MarketWatch;
use App\Entity\Item;
use App\Enum\PriceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MarketWatchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('item', EntityType::class, [
                'class' => Item::class,
                'choice_label' => 'name',
                'label' => 'Item à surveiller',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('lotSize', IntegerType::class, [
                'label' => 'Taille du lot surveillé',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('observedPrice', IntegerType::class, [
                'label' => 'Prix observé (kamas)',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('priceType', EnumType::class, [
                'class' => PriceType::class,
                'label' => 'Type de prix',
                'choice_label' => function(PriceType $choice): string {
                    return match($choice) {
                        PriceType::BUY => 'Prix d\'achat',
                        PriceType::SELL => 'Prix de vente',
                    };
                },
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
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
            'data_class' => MarketWatch::class,
        ]);
    }
}