<?php
namespace Blog\Form;

class PostDeleteForm extends PostForm
{
    protected $validationGroup = array('id');

    protected $baseFilters = array(
        'id' => array(
            'name' => 'id',
            'required' => true,
            'filters' => array(
               array('name' => 'Int'),
            ),
        ),
    );
}
