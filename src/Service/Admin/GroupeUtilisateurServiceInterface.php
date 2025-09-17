<?php

namespace App\Service\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

interface GroupeUtilisateurServiceInterface
{
    public function getGroupe(int $id): JsonResponse;
    public function updateGroupe(int $id, Request $request): JsonResponse;
    public function createGroupe(Request $request): JsonResponse;
    public function deleteGroupe(int $id): JsonResponse;
    public function toggleGroupe(int $id): JsonResponse;
}