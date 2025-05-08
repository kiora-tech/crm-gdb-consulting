<?php

namespace App\Controller;

use App\Entity\Comment;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/comment', name: 'app_comment')]
final class CommentController extends CustomerInfoController
{
    public function getEntityClass(): string
    {
        return Comment::class;
    }
}
