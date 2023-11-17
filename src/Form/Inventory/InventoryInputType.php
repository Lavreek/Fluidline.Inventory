<?php

namespace App\Form\Inventory;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class InventoryInputType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('inventory_type', TextType::class, [
                'label' => 'Тип ресурса',
                'attr' => [
                    'placeholder' => 'например, Фитинги',
                ]
            ])
            ->add('inventory_serial', TextType::class, [
                'label' => 'Серия',
                'attr' => [
                    'placeholder' => 'например, CUA'
                ]
            ])
            ->add('inventory_file', FileType::class, [
                'label' => 'Файл характеристик (CSV файл) разделитель ";"',
                'constraints' => [
                    new File([
                        'maxSize' => '512k',
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain'
                        ],
                        'mimeTypesMessage' => 'Файл не подходит для загрузки',
                    ])
                ],
            ])
            ->add('inventory_submit', SubmitType::class, [
                'label' => 'Создать'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
            'action' => '/constructor/create',
            'method' => 'POST',
        ]);
    }
}
