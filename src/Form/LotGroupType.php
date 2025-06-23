<?php

namespace App\Form;

use App\Entity\LotGroup;
use App\Entity\Item;
use App\Enum\LotStatus;
use App\Enum\SaleUnit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LotGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        
        if ($isEdit) {
            // En mode édition, on affiche juste l'item actuel (read-only via template)
            // Pas besoin de champ dans le formulaire car l'item ne peut pas être modifié
        } else {
            // En mode création, champ de recherche + champ caché
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
            
            // Champ caché pour stocker l'ID de l'item sélectionné
            $builder->add('item', HiddenType::class, [
                'mapped' => false,
                'attr' => [
                    'data-autocomplete-target' => 'hiddenId'
                ]
            ]);
        }
        
        $builder
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
            ]);

        // Configuration du champ status selon le mode
        $statusConfig = [
            'class' => LotStatus::class,
            'choice_label' => function(LotStatus $choice): string {
                return match($choice) {
                    LotStatus::AVAILABLE => 'Disponible',
                    LotStatus::SOLD => 'Vendu',
                };
            },
            'label' => 'Statut',
            'attr' => ['class' => 'form-input']
        ];

        // Seulement définir une valeur par défaut en mode création
        if (!$isEdit) {
            $statusConfig['data'] = LotStatus::AVAILABLE;
        }

        $builder->add('status', EnumType::class, $statusConfig);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LotGroup::class,
            'is_edit' => false,
            'current_item' => null,
        ]);
    }
}