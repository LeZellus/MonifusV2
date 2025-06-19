<?php

namespace App\Form;

use App\Entity\LotGroup;
use App\Entity\Item;
use App\Enum\LotStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Enum\SaleUnit;

class LotGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('item', EntityType::class, [
                'class' => Item::class,
                'choice_label' => 'name',
                'label' => 'Item',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('saleUnit', EnumType::class, [
                'class' => SaleUnit::class,
                'choice_label' => fn(SaleUnit $choice) => $choice->getLabel(),
                'label' => 'Unité de vente Dofus',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('lotSize', IntegerType::class, [
                'label' => 'Taille du lot',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('buyPricePerLot', IntegerType::class, [
                'label' => 'Prix d\'achat par lot (kamas)',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('sellPricePerLot', IntegerType::class, [
                'label' => 'Prix de vente par lot (kamas)',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('status', EnumType::class, [
                'class' => LotStatus::class,
                'choice_label' => function(LotStatus $choice): string {
                    return match($choice) {
                        LotStatus::AVAILABLE => 'Disponible',
                        LotStatus::SOLD => 'Vendu',
                    };
                },
                'label' => 'Statut',
                'data' => LotStatus::AVAILABLE,
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LotGroup::class,
        ]);
    }
}