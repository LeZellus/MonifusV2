<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('pseudonymeWebsite', TextType::class, [
                'label' => 'Pseudo sur le site',
                'required' => false,
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('pseudonymeDofus', TextType::class, [
                'label' => 'Pseudo Dofus principal',
                'required' => false,
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500', 'rows' => 3]
            ])
            ->add('discordId', TextType::class, [
                'label' => 'Discord ID',
                'required' => false,
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('youtubeUrl', UrlType::class, [
                'label' => 'YouTube',
                'required' => false,
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('twitchUrl', UrlType::class, [
                'label' => 'Twitch',
                'required' => false,
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500']
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit faire au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                ],
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-blue-500',
                    'placeholder' => 'Laissez vide pour ne pas changer'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}