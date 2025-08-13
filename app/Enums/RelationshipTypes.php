<?php

namespace App;

enum RelationshipTypes: string
{
    case MOTHER = 'Mother';
    case FATHER = 'Father';
    case WIFE = 'Wife';
    case HUSBAND = 'Husband';
    case SISTER = 'Sister';
    case BROTHER = 'Brother';
    case FRIEND = 'Friend';
    case UNCLE = 'Uncle';
}
