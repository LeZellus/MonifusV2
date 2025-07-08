<?php

namespace App\Form;

use App\Entity\MarketWatch;
use App\Entity\Item;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class MarketWatchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        $preselectedItem = $options['preselected_item'] ?? null;
        
        if ($isEdit) {
            // En mode édition, pas de champ item (read-only via template)
        } elseif ($preselectedItem) {
            // Item prérempli - champ caché
            $builder->add('item', HiddenType::class, [
                'data' => $preselectedItem->getId(),
                'mapped' => false,
            ]);
        } else {
            // Mode création normal avec recherche
            $builder->add('itemSearch', TextType::class, [
                'label' => 'Rechercher une ressource',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Tapez le nom de la ressource...',
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
            ->add('observedAt', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Date d\'observation',
                'data' => new \DateTimeImmutable(),
                'attr' => ['class' => 'form-input']
            ])
            ->add('pricePerUnit', IntegerType::class, [
                'label' => 'Prix à l\'unité (x1)',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'ex: 1500'
                ]
            ])
            ->add('pricePer10', IntegerType::class, [
                'label' => 'Prix par 10 (x10)',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'ex: 14500'
                ]
            ])
            ->add('pricePer100', IntegerType::class, [
                'label' => 'Prix par 100 (x100)',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'ex: 140000'
                ]
            ])
            ->add('pricePer1000', IntegerType::class, [
                'label' => 'Prix par 1000 (x1000)',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'ex: 1350000'
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes sur le marché',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'rows' => 3,
                    'placeholder' => 'ex: Prix en hausse, forte demande, peu d\'offres...'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MarketWatch::class,
            'is_edit' => false,
            'preselected_item' => null,
            'constraints' => [
                new Callback([$this, 'validateAtLeastOnePrice'])
            ]
        ]);
    }

    public function validateAtLeastOnePrice(MarketWatch $marketWatch, ExecutionContextInterface $context): void
    {
        if (!$marketWatch->hasAnyPrice()) {
            $context->buildViolation('Vous devez renseigner au moins un prix (x1, x10, x100 ou x1000)')
                ->atPath('pricePerUnit')
                ->addViolation();
        }
    }
}