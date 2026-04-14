<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter your email'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                ],
            ])
            ->add('fullName', TextType::class, [
                'label' => 'Full Name',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter your full name'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 2, 'max' => 120]),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Enter password'],
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Confirm password'],
                ],
                'invalid_message' => 'Passwords do not match.',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['min' => 8, 'max' => 255]),
                ],
                'mapped' => false,
            ])
            ->add('roleChoice', ChoiceType::class, [
                'label' => 'Type de compte',
                'choices' => [
                    'Client' => 'CLIENT',
                    'Administrateur' => 'ADMIN',
                ],
                'attr' => ['class' => 'form-control'],
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('cin', TextType::class, [
                'label' => 'CIN (Optional - for Clients)',
                'attr' => ['class' => 'form-control', 'placeholder' => 'National ID number'],
                'required' => false,
                'mapped' => false,
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone Number (Optional - for Clients)',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Phone number'],
                'required' => false,
                'mapped' => false,
            ])
            ->add('adminCode', PasswordType::class, [
                'label' => 'Code administrateur (requis si ADMIN)',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Code admin'],
                'required' => false,
                'mapped' => false,
            ])
            ->add('faceDescriptor', HiddenType::class, [
                'required' => false,
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
