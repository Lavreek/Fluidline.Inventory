<?php

namespace App\Form\Remover;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class BySerialType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', TextType::class, [
                'label' => 'Тип ресурса',
                'attr' => [
                    'placeholder' => 'например, Фитинги',
                ]
            ])
            ->add('serial', TextType::class, [
                'label' => 'Серия',
                'attr' => [
                    'placeholder' => 'например, CUA'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Удалить'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
