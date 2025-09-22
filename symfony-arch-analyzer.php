#!/usr/bin/env php
<?php
/**
 * Script d'analyse d'architecture pour TechnoProd - Application Symfony
 * Génère un fichier DOT pour visualisation avec GraphViz
 */

class TechnoProdArchitectureAnalyzer
{
    private array $routes = [];
    private array $controllers = [];
    private array $services = [];
    private array $entities = [];
    private array $dependencies = [];
    private array $adminControllers = [];
    private array $forms = [];
    private array $repositories = [];
   
    public function __construct(private string $projectPath)
    {
    }
   
    public function analyze(): void
    {
        echo "🔍 Analyse de l'architecture TechnoProd...\n";
        $this->analyzeRoutes();
        $this->analyzeControllers();
        $this->analyzeAdminControllers();
        $this->analyzeServices();
        $this->analyzeEntities();
        $this->analyzeForms();
        $this->analyzeRepositories();
        $this->analyzeDependencies();
        echo "✅ Analyse terminée.\n";
    }
   
    private function analyzeRoutes(): void
    {
        echo "📍 Analyse des routes...\n";
        
        // Analyse des fichiers de configuration des routes
        $routesFile = $this->projectPath . '/config/routes.yaml';
        if (file_exists($routesFile)) {
            $content = file_get_contents($routesFile);
            if (function_exists('yaml_parse')) {
                $routes = yaml_parse($content);
                if ($routes) {
                    foreach ($routes as $name => $config) {
                        $this->routes[$name] = [
                            'path' => $config['path'] ?? '',
                            'controller' => $config['controller'] ?? '',
                            'methods' => $config['methods'] ?? ['GET']
                        ];
                    }
                }
            }
        }
       
        // Analyse des annotations dans les controllers
        $this->analyzeRouteAnnotations();
    }
   
    private function analyzeRouteAnnotations(): void
    {
        $controllerPath = $this->projectPath . '/src/Controller';
        if (!is_dir($controllerPath)) return;
       
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllerPath)
        );
       
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
           
            $content = file_get_contents($file->getPathname());
           
            // Recherche des annotations Route (Attributes PHP 8)
            preg_match_all(
                '/#\[Route\([\'"]([^\'"]+)[\'"].*?\)\].*?public function (\w+)/s',
                $content,
                $matches
            );
           
            for ($i = 0; $i < count($matches[0]); $i++) {
                $path = $matches[1][$i];
                $method = $matches[2][$i];
                $controller = $this->extractClassName($file->getPathname());
               
                $this->routes[$controller . '::' . $method] = [
                    'path' => $path,
                    'controller' => $controller,
                    'method' => $method,
                    'file' => $file->getPathname()
                ];
            }
        }
    }
   
    private function analyzeControllers(): void
    {
        echo "🎮 Analyse des controllers...\n";
        
        $controllerPath = $this->projectPath . '/src/Controller';
        if (!is_dir($controllerPath)) return;
       
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllerPath)
        );
       
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
           
            $className = $this->extractClassName($file->getPathname());
            $this->controllers[$className] = [
                'file' => $file->getPathname(),
                'methods' => $this->extractMethods($file->getPathname()),
                'dependencies' => $this->extractConstructorDependencies($file->getPathname()),
                'type' => $this->getControllerType($file->getPathname())
            ];
        }
    }
    
    private function analyzeAdminControllers(): void
    {
        echo "👨‍💼 Analyse des controllers Admin...\n";
        
        $adminPath = $this->projectPath . '/src/Controller/Admin';
        if (!is_dir($adminPath)) return;
       
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($adminPath)
        );
       
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
           
            $className = $this->extractClassName($file->getPathname());
            $this->adminControllers[$className] = [
                'file' => $file->getPathname(),
                'methods' => $this->extractMethods($file->getPathname()),
                'dependencies' => $this->extractConstructorDependencies($file->getPathname())
            ];
        }
    }
   
    private function analyzeServices(): void
    {
        echo "⚙️ Analyse des services...\n";
        
        // Analyse du fichier services.yaml
        $servicesFile = $this->projectPath . '/config/services.yaml';
        if (file_exists($servicesFile)) {
            $content = file_get_contents($servicesFile);
            if (function_exists('yaml_parse')) {
                $services = yaml_parse($content);
                if (isset($services['services'])) {
                    foreach ($services['services'] as $id => $config) {
                        if (is_array($config)) {
                            $this->services[$id] = [
                                'class' => $config['class'] ?? $id,
                                'arguments' => $config['arguments'] ?? [],
                                'type' => 'configured'
                            ];
                        }
                    }
                }
            }
        }
       
        // Analyse des classes Service
        $this->analyzeServiceClasses();
    }
   
    private function analyzeServiceClasses(): void
    {
        $servicePath = $this->projectPath . '/src/Service';
        if (!is_dir($servicePath)) return;
       
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($servicePath)
        );
       
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
           
            $className = $this->extractClassName($file->getPathname());
            $this->services[$className] = [
                'file' => $file->getPathname(),
                'dependencies' => $this->extractConstructorDependencies($file->getPathname()),
                'type' => $this->getServiceType($file->getPathname())
            ];
        }
    }
   
    private function analyzeEntities(): void
    {
        echo "📊 Analyse des entités...\n";
        
        $entityPath = $this->projectPath . '/src/Entity';
        if (!is_dir($entityPath)) return;
       
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($entityPath)
        );
       
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
           
            $className = $this->extractClassName($file->getPathname());
            $content = file_get_contents($file->getPathname());
           
            // Extraction des relations Doctrine
            $relations = [];
           
            // OneToMany
            preg_match_all('/#\[OneToMany.*?targetEntity:\s*(\w+)::class/s', $content, $matches);
            foreach ($matches[1] as $target) {
                $relations[] = ['type' => 'OneToMany', 'target' => $target];
            }
           
            // ManyToOne
            preg_match_all('/#\[ManyToOne.*?targetEntity:\s*(\w+)::class/s', $content, $matches);
            foreach ($matches[1] as $target) {
                $relations[] = ['type' => 'ManyToOne', 'target' => $target];
            }
           
            // ManyToMany
            preg_match_all('/#\[ManyToMany.*?targetEntity:\s*(\w+)::class/s', $content, $matches);
            foreach ($matches[1] as $target) {
                $relations[] = ['type' => 'ManyToMany', 'target' => $target];
            }
            
            // OneToOne
            preg_match_all('/#\[OneToOne.*?targetEntity:\s*(\w+)::class/s', $content, $matches);
            foreach ($matches[1] as $target) {
                $relations[] = ['type' => 'OneToOne', 'target' => $target];
            }
           
            $this->entities[$className] = [
                'file' => $file->getPathname(),
                'relations' => $relations,
                'properties' => $this->extractEntityProperties($content)
            ];
        }
    }
    
    private function analyzeForms(): void
    {
        echo "📝 Analyse des formulaires...\n";
        
        $formPath = $this->projectPath . '/src/Form';
        if (!is_dir($formPath)) return;
       
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($formPath)
        );
       
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
           
            $className = $this->extractClassName($file->getPathname());
            $content = file_get_contents($file->getPathname());
            
            $this->forms[$className] = [
                'file' => $file->getPathname(),
                'entity' => $this->extractFormEntity($content)
            ];
        }
    }
    
    private function analyzeRepositories(): void
    {
        echo "💾 Analyse des repositories...\n";
        
        $repoPath = $this->projectPath . '/src/Repository';
        if (!is_dir($repoPath)) return;
       
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($repoPath)
        );
       
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
           
            $className = $this->extractClassName($file->getPathname());
            $content = file_get_contents($file->getPathname());
            
            $this->repositories[$className] = [
                'file' => $file->getPathname(),
                'entity' => $this->extractRepositoryEntity($content),
                'methods' => $this->extractMethods($file->getPathname())
            ];
        }
    }
   
    private function analyzeDependencies(): void
    {
        echo "🔗 Analyse des dépendances...\n";
        
        // Analyse des injections de dépendances dans les controllers
        foreach ($this->controllers as $controller => $data) {
            if (isset($data['dependencies'])) {
                foreach ($data['dependencies'] as $dep) {
                    $this->dependencies[] = [
                        'from' => $controller,
                        'to' => $dep,
                        'type' => 'inject'
                    ];
                }
            }
        }
        
        // Analyse des dépendances des controllers admin
        foreach ($this->adminControllers as $controller => $data) {
            if (isset($data['dependencies'])) {
                foreach ($data['dependencies'] as $dep) {
                    $this->dependencies[] = [
                        'from' => $controller,
                        'to' => $dep,
                        'type' => 'inject'
                    ];
                }
            }
        }
       
        // Analyse des dépendances des services
        foreach ($this->services as $service => $data) {
            if (isset($data['dependencies'])) {
                foreach ($data['dependencies'] as $dep) {
                    $this->dependencies[] = [
                        'from' => $service,
                        'to' => $dep,
                        'type' => 'inject'
                    ];
                }
            }
        }
       
        // Relations entre entités
        foreach ($this->entities as $entity => $data) {
            foreach ($data['relations'] as $relation) {
                $this->dependencies[] = [
                    'from' => $entity,
                    'to' => $relation['target'],
                    'type' => 'relation:' . $relation['type']
                ];
            }
        }
    }
   
    private function extractClassName(string $filePath): string
    {
        $content = file_get_contents($filePath);
       
        // Extraction du namespace
        preg_match('/namespace\s+([\w\\\\]+);/', $content, $namespaceMatch);
        $namespace = $namespaceMatch[1] ?? '';
       
        // Extraction du nom de la classe
        preg_match('/class\s+(\w+)/', $content, $classMatch);
        $className = $classMatch[1] ?? '';
       
        return $namespace ? $namespace . '\\' . $className : $className;
    }
   
    private function extractMethods(string $filePath): array
    {
        $content = file_get_contents($filePath);
        preg_match_all('/public function (\w+)\(/', $content, $matches);
        return $matches[1] ?? [];
    }
   
    private function extractConstructorDependencies(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $dependencies = [];
       
        // Recherche du constructeur
        if (preg_match('/public function __construct\((.*?)\)/s', $content, $match)) {
            $params = $match[1];
           
            // Extraction des types des paramètres
            preg_match_all('/(\w+)\s+\$\w+/', $params, $typeMatches);
           
            foreach ($typeMatches[1] as $type) {
                if (!in_array($type, ['string', 'int', 'bool', 'array', 'float', 'mixed'])) {
                    $dependencies[] = $type;
                }
            }
        }
       
        return $dependencies;
    }
    
    private function getControllerType(string $filePath): string
    {
        if (strpos($filePath, '/Admin/') !== false) {
            return 'admin';
        } elseif (strpos($filePath, '/Api/') !== false) {
            return 'api';
        } else {
            return 'web';
        }
    }
    
    private function getServiceType(string $filePath): string
    {
        if (strpos($filePath, '/Admin/') !== false) {
            return 'admin';
        } elseif (strpos($filePath, 'Service.php') !== false) {
            return 'business';
        } else {
            return 'technical';
        }
    }
    
    private function extractEntityProperties(string $content): array
    {
        $properties = [];
        preg_match_all('/#\[ORM\\\\Column.*?\]\s*private\s+[\w\?]+\s+\$(\w+)/', $content, $matches);
        return $matches[1] ?? [];
    }
    
    private function extractFormEntity(string $content): ?string
    {
        preg_match('/\'data_class\'\s*=>\s*(\w+)::class/', $content, $match);
        return $match[1] ?? null;
    }
    
    private function extractRepositoryEntity(string $content): ?string
    {
        preg_match('/class\s+\w+Repository\s+extends\s+ServiceEntityRepository/', $content, $match);
        if ($match) {
            preg_match('/parent::__construct\(\$registry,\s*(\w+)::class\)/', $content, $entityMatch);
            return $entityMatch[1] ?? null;
        }
        return null;
    }
   
    public function generateDot(): string
    {
        echo "🎨 Génération du diagramme DOT...\n";
        
        $dot = "digraph TechnoProdArchitecture {\n";
        $dot .= "    rankdir=TB;\n";
        $dot .= "    node [shape=box, style=rounded];\n";
        $dot .= "    overlap=false;\n";
        $dot .= "    splines=true;\n\n";
       
        // Subgraph pour les entités (cœur métier)
        $dot .= "    subgraph cluster_entities {\n";
        $dot .= "        label=\"📊 Entités Métier TechnoProd\";\n";
        $dot .= "        style=filled;\n";
        $dot .= "        fillcolor=lightyellow;\n";
        $dot .= "        fontsize=14;\n";
        $dot .= "        fontname=\"Arial Bold\";\n";
        foreach ($this->entities as $name => $data) {
            $shortName = $this->getShortName($name);
            $propertyCount = count($data['properties']);
            $dot .= "        \"$shortName\" [shape=box3d, label=\"$shortName\\n($propertyCount propriétés)\"];\n";
        }
        $dot .= "    }\n\n";
        
        // Subgraph pour les controllers web
        $dot .= "    subgraph cluster_controllers {\n";
        $dot .= "        label=\"🎮 Controllers Web\";\n";
        $dot .= "        style=filled;\n";
        $dot .= "        fillcolor=lightblue;\n";
        $dot .= "        fontsize=14;\n";
        $dot .= "        fontname=\"Arial Bold\";\n";
        foreach ($this->controllers as $name => $data) {
            if ($data['type'] === 'web') {
                $shortName = $this->getShortName($name);
                $methodCount = count($data['methods']);
                $dot .= "        \"$shortName\" [shape=component, label=\"$shortName\\n($methodCount actions)\"];\n";
            }
        }
        $dot .= "    }\n\n";
        
        // Subgraph pour les controllers admin
        $dot .= "    subgraph cluster_admin_controllers {\n";
        $dot .= "        label=\"👨‍💼 Controllers Admin\";\n";
        $dot .= "        style=filled;\n";
        $dot .= "        fillcolor=lightcyan;\n";
        $dot .= "        fontsize=14;\n";
        $dot .= "        fontname=\"Arial Bold\";\n";
        foreach ($this->adminControllers as $name => $data) {
            $shortName = $this->getShortName($name);
            $methodCount = count($data['methods']);
            $dot .= "        \"$shortName\" [shape=component, label=\"$shortName\\n($methodCount actions)\"];\n";
        }
        $dot .= "    }\n\n";
       
        // Subgraph pour les services
        $dot .= "    subgraph cluster_services {\n";
        $dot .= "        label=\"⚙️ Services Métier\";\n";
        $dot .= "        style=filled;\n";
        $dot .= "        fillcolor=lightgreen;\n";
        $dot .= "        fontsize=14;\n";
        $dot .= "        fontname=\"Arial Bold\";\n";
        foreach ($this->services as $name => $data) {
            if ($data['type'] === 'business') {
                $shortName = $this->getShortName($name);
                $dot .= "        \"$shortName\" [shape=ellipse];\n";
            }
        }
        $dot .= "    }\n\n";
        
        // Subgraph pour les repositories
        $dot .= "    subgraph cluster_repositories {\n";
        $dot .= "        label=\"💾 Repositories\";\n";
        $dot .= "        style=filled;\n";
        $dot .= "        fillcolor=lightpink;\n";
        $dot .= "        fontsize=14;\n";
        $dot .= "        fontname=\"Arial Bold\";\n";
        foreach ($this->repositories as $name => $data) {
            $shortName = $this->getShortName($name);
            $methodCount = count($data['methods']);
            $dot .= "        \"$shortName\" [shape=cylinder, label=\"$shortName\\n($methodCount méthodes)\"];\n";
        }
        $dot .= "    }\n\n";
        
        // Subgraph pour les formulaires
        $dot .= "    subgraph cluster_forms {\n";
        $dot .= "        label=\"📝 Formulaires Symfony\";\n";
        $dot .= "        style=filled;\n";
        $dot .= "        fillcolor=lavender;\n";
        $dot .= "        fontsize=14;\n";
        $dot .= "        fontname=\"Arial Bold\";\n";
        foreach ($this->forms as $name => $data) {
            $shortName = $this->getShortName($name);
            $dot .= "        \"$shortName\" [shape=note];\n";
        }
        $dot .= "    }\n\n";
       
        // Dépendances entre entités (relations Doctrine)
        foreach ($this->dependencies as $dep) {
            if (strpos($dep['type'], 'relation:') === 0) {
                $from = $this->getShortName($dep['from']);
                $to = $this->getShortName($dep['to']);
                $relationType = str_replace('relation:', '', $dep['type']);
                $color = match($relationType) {
                    'OneToMany' => 'blue',
                    'ManyToOne' => 'red',
                    'ManyToMany' => 'purple',
                    'OneToOne' => 'green',
                    default => 'black'
                };
                $label = match($relationType) {
                    'OneToMany' => '1:N',
                    'ManyToOne' => 'N:1',
                    'ManyToMany' => 'N:N',
                    'OneToOne' => '1:1',
                    default => ''
                };
                $dot .= "    \"$from\" -> \"$to\" [color=$color, label=\"$label\", fontsize=10];\n";
            }
        }
        
        // Dépendances d'injection (plus légères)
        foreach ($this->dependencies as $dep) {
            if ($dep['type'] === 'inject') {
                $from = $this->getShortName($dep['from']);
                $to = $this->getShortName($dep['to']);
                $dot .= "    \"$from\" -> \"$to\" [style=dashed, color=gray];\n";
            }
        }
       
        $dot .= "}\n";
       
        return $dot;
    }
   
    private function getShortName(string $fullName): string
    {
        $parts = explode('\\', $fullName);
        return end($parts);
    }
   
    public function exportToJson(): string
    {
        return json_encode([
            'summary' => [
                'total_entities' => count($this->entities),
                'total_controllers' => count($this->controllers),
                'total_admin_controllers' => count($this->adminControllers),
                'total_services' => count($this->services),
                'total_repositories' => count($this->repositories),
                'total_forms' => count($this->forms),
                'total_routes' => count($this->routes),
                'total_dependencies' => count($this->dependencies)
            ],
            'routes' => $this->routes,
            'controllers' => $this->controllers,
            'admin_controllers' => $this->adminControllers,
            'services' => $this->services,
            'entities' => $this->entities,
            'repositories' => $this->repositories,
            'forms' => $this->forms,
            'dependencies' => $this->dependencies
        ], JSON_PRETTY_PRINT);
    }
    
    public function generateReport(): string
    {
        $report = "# 📊 Rapport d'Architecture TechnoProd\n\n";
        $report .= "## 📈 Statistiques Générales\n\n";
        $report .= "- **Entités :** " . count($this->entities) . "\n";
        $report .= "- **Controllers Web :** " . count(array_filter($this->controllers, fn($c) => $c['type'] === 'web')) . "\n";
        $report .= "- **Controllers Admin :** " . count($this->adminControllers) . "\n";
        $report .= "- **Services :** " . count($this->services) . "\n";
        $report .= "- **Repositories :** " . count($this->repositories) . "\n";
        $report .= "- **Formulaires :** " . count($this->forms) . "\n";
        $report .= "- **Routes :** " . count($this->routes) . "\n";
        $report .= "- **Dépendances :** " . count($this->dependencies) . "\n\n";
        
        $report .= "## 🏗️ Architecture Détaillée\n\n";
        
        $report .= "### 📊 Entités Principales\n\n";
        foreach ($this->entities as $name => $data) {
            $shortName = $this->getShortName($name);
            $propertyCount = count($data['properties']);
            $relationCount = count($data['relations']);
            $report .= "- **{$shortName}** : {$propertyCount} propriétés, {$relationCount} relations\n";
        }
        
        $report .= "\n### 🎮 Controllers\n\n";
        foreach ($this->controllers as $name => $data) {
            $shortName = $this->getShortName($name);
            $methodCount = count($data['methods']);
            $type = ucfirst($data['type']);
            $report .= "- **{$shortName}** ({$type}) : {$methodCount} actions\n";
        }
        
        return $report;
    }
}

// Utilisation du script
if (php_sapi_name() === 'cli') {
    $projectPath = $argv[1] ?? getcwd();
   
    if (!is_dir($projectPath)) {
        echo "❌ Erreur: Le chemin '$projectPath' n'est pas un répertoire valide.\n";
        exit(1);
    }
    
    if (!file_exists($projectPath . '/src')) {
        echo "❌ Erreur: Ce n'est pas un projet Symfony (dossier src/ introuvable).\n";
        exit(1);
    }
   
    echo "🚀 Analyse de l'architecture TechnoProd dans: $projectPath\n\n";
   
    $analyzer = new TechnoProdArchitectureAnalyzer($projectPath);
    $analyzer->analyze();
    
    echo "\n📊 Génération des rapports...\n";
   
    // Génération du fichier DOT
    $dotContent = $analyzer->generateDot();
    file_put_contents('technoprod-architecture.dot', $dotContent);
    echo "✅ Fichier DOT généré: technoprod-architecture.dot\n";
   
    // Génération du fichier JSON
    $jsonContent = $analyzer->exportToJson();
    file_put_contents('technoprod-architecture.json', $jsonContent);
    echo "✅ Fichier JSON généré: technoprod-architecture.json\n";
    
    // Génération du rapport Markdown
    $reportContent = $analyzer->generateReport();
    file_put_contents('technoprod-architecture-report.md', $reportContent);
    echo "✅ Rapport Markdown généré: technoprod-architecture-report.md\n";
   
    // Tentative de génération d'images avec GraphViz si disponible
    $dotCommand = shell_exec('which dot 2>/dev/null');
    if ($dotCommand && trim($dotCommand)) {
        echo "🎨 Génération des images avec GraphViz...\n";
        shell_exec('dot -Tpng technoprod-architecture.dot -o technoprod-architecture.png 2>/dev/null');
        if (file_exists('technoprod-architecture.png')) {
            echo "✅ Image PNG générée: technoprod-architecture.png\n";
        }
        shell_exec('dot -Tsvg technoprod-architecture.dot -o technoprod-architecture.svg 2>/dev/null');
        if (file_exists('technoprod-architecture.svg')) {
            echo "✅ Image SVG générée: technoprod-architecture.svg\n";
        }
    } else {
        echo "⚠️ GraphViz (dot) n'est pas installé. Pour installer :\n";
        echo "   sudo apt-get install graphviz\n";
    }
    
    echo "\n🎉 Analyse terminée avec succès !\n";
    echo "📁 Fichiers générés dans le répertoire courant.\n";
}