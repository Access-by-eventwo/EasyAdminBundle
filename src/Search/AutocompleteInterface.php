<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Search;

interface AutocompleteInterface
{
    public function find($entity, $query, $page = 1): array;
}
