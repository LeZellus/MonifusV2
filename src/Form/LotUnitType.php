<?php

namespace App\Form;

use App\Entity\LotUnit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class LotUnitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $lotGroup = $options['lot_group'];
        $maxQuantity = $lotGroup ? $lotGroup->getLotSize() : 1;

        $builder
            ->add('quantitySold', IntegerType::class, [
                'label' => 'Quantité vendue',
                'attr' => [
                    'class' => 'form-input',
                    'min' => 1,
                    'max' => $maxQuantity,
                    'placeholder' => "Max: {$maxQuantity} lots"
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La quantité est obligatoire']),
                    new Range([
                        'min' => 1,
                        'max' => $maxQuantity,
                        'notInRangeMessage' => 'La quantité doit être entre 1 et {{ limit }}'
                    ])
                ],
                'mapped' => false, // On va gérer ça manuellement dans le contrôleur
            ])
            ->add('actualSellPrice', IntegerType::class, [
                'label' => 'Prix de vente réel (kamas)',
                'attr' => ['class' => 'form-input']
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes sur la vente',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'rows' => 3
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LotUnit::class,
            'lot_group' => null,
        ]);
    }
}