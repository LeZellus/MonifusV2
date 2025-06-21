<?php

namespace App\Form;

use App\Entity\LotGroup;
use App\Entity\Item;
use App\Enum\LotStatus;
use App\Enum\SaleUnit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LotGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        
        // Champ de recherche uniquement pour la création
        if (!$isEdit) {
            $builder->add('itemSearch', TextType::class, [
                'label' => 'Rechercher un item',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Tapez le nom de l\'item...',
                    'data-autocomplete-target' => 'input',
                    'data-action' => 'input->autocomplete#search'
                ]
            ]);
        }
        
        $builder
            ->add('item', EntityType::class, [
                'class' => Item::class,
                'choice_label' => 'name',
                'label' => 'Item',
                'attr' => array_merge(
                    ['class' => 'form-input'],
                    !$isEdit ? ['data-autocomplete-target' => 'hiddenId', 'style' => 'display: none;'] : []
                )
            ])
            ->add('saleUnit', EnumType::class, [
                'class' => SaleUnit::class,
                'choice_label' => fn(SaleUnit $choice) => $choice->getLabel(),
                'label' => 'Unité de vente Dofus',
                'attr' => ['class' => 'form-input']
            ])
            ->add('lotSize', IntegerType::class, [
                'label' => 'Taille du lot',
                'attr' => ['class' => 'form-input']
            ])
            ->add('buyPricePerLot', IntegerType::class, [
                'label' => 'Prix d\'achat par lot (kamas)',
                'attr' => ['class' => 'form-input']
            ])
            ->add('sellPricePerLot', IntegerType::class, [
                'required' => false,
                'label' => 'Prix de vente par lot (optionnel)',
                'attr' => ['class' => 'form-input', 'placeholder' => 'À définir lors de la vente']
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
                'attr' => ['class' => 'form-input']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LotGroup::class,
            'is_edit' => false,
        ]);
    }
}