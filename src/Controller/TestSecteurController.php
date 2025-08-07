<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestSecteurController extends AbstractController
{
    #[Route('/test-secteur', name: 'app_test_secteur')]
    public function testSecteur(): Response
    {
        return new Response('
<!DOCTYPE html>
<html>
<head>
    <title>Test Secteur</title>
</head>
<body>
    <h1>Test API Secteur</h1>
    <div id="result"></div>
    <script>
        fetch("/admin/secteurs/all-geo-data")
        .then(response => response.json())
        .then(data => {
            document.getElementById("result").innerHTML = "<pre>" + JSON.stringify(data, null, 2) + "</pre>";
        })
        .catch(error => {
            document.getElementById("result").innerHTML = "Error: " + error.message;
        });
    </script>
</body>
</html>
        ');
    }
}