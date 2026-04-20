/**
 * WysiwygNotesManager - Gestionnaire réutilisable pour les éditeurs WYSIWYG des notes
 * Basé sur la version fonctionnelle de /devis/new
 * @version 1.0.0
 */
class WysiwygNotesManager {
    constructor(prefix, publicFieldName = 'note_publique', privateFieldName = 'note_privee') {
        this.prefix = prefix;
        this.publicFieldName = publicFieldName;
        this.privateFieldName = privateFieldName;
        this.quillPublique = null;
        this.quillPrivee = null;
        this.initialized = false;
    }

    /**
     * Handler personnalisé pour l'upload d'images dans le WYSIWYG
     */
    imageHandler() {
        const currentEditor = this.quill;
        showImageDimensionsModal(currentEditor);
    }

    /**
     * Initialise les éditeurs WYSIWYG
     */
    init() {
        if (this.initialized) {
            console.warn(`WysiwygNotesManager déjà initialisé pour le préfixe: ${this.prefix}`);
            return;
        }

        console.log(`🎯 Initialisation WysiwygNotesManager pour préfixe: ${this.prefix}`);

        // Configuration Quill (identique à new.html.twig)
        const Font = Quill.import('formats/font');
        Font.whitelist = ['arial', 'helvetica', 'times', 'georgia', 'verdana', 'courier'];
        Quill.register(Font, true);

        const Size = Quill.import('formats/size');
        Size.whitelist = ['10px', '12px', '14px', '16px', '18px', '20px', '22px', '24px'];
        Quill.register(Size, true);

        // Options communes
        const quillOptions = {
            theme: 'snow',
            modules: {
                toolbar: {
                    handlers: {
                        'image': this.imageHandler
                    }
                }
            }
        };

        // Initialisation de l'éditeur pour note publique
        const optionsNotePublique = { ...quillOptions };
        optionsNotePublique.modules.toolbar.container = `#toolbar-note-publique-${this.prefix}`;
        optionsNotePublique.placeholder = '';

        this.quillPublique = new Quill(`#editor-note-publique-${this.prefix}`, optionsNotePublique);

        // Initialisation de l'éditeur pour note privée
        const optionsNotePrivee = { ...quillOptions };
        optionsNotePrivee.modules.toolbar.container = `#toolbar-note-privee-${this.prefix}`;
        optionsNotePrivee.placeholder = '';

        this.quillPrivee = new Quill(`#editor-note-privee-${this.prefix}`, optionsNotePrivee);

        // Charger le contenu initial si disponible
        this.loadInitialContent();

        // Synchronisation des éditeurs avec les champs cachés du formulaire
        this.setupSynchronization();

        // Correction forcée des largeurs des dropdowns après initialisation (comme dans new.html.twig)
        this.fixPickerWidths();

        this.initialized = true;
        console.log(`✅ WysiwygNotesManager initialisé pour préfixe: ${this.prefix}`);
    }

    /**
     * Charge le contenu initial dans les éditeurs
     */
    loadInitialContent() {
        const publicField = document.getElementById(`note-publique-${this.prefix}`);
        const privateField = document.getElementById(`note-privee-${this.prefix}`);

        if (publicField && this.quillPublique) {
            const initialContent = publicField.getAttribute('data-initial-value') || '';
            if (initialContent) {
                this.quillPublique.root.innerHTML = initialContent;
            }
        }

        if (privateField && this.quillPrivee) {
            const initialContent = privateField.getAttribute('data-initial-value') || '';
            if (initialContent) {
                this.quillPrivee.root.innerHTML = initialContent;
            }
        }
    }

    /**
     * Configure la synchronisation entre les éditeurs et les champs cachés
     */
    setupSynchronization() {
        const publicField = document.getElementById(`note-publique-${this.prefix}`);
        const privateField = document.getElementById(`note-privee-${this.prefix}`);

        // Synchronisation éditeur publique
        if (this.quillPublique && publicField) {
            this.quillPublique.on('text-change', () => {
                const content = this.quillPublique.root.innerHTML;
                publicField.value = content === '<p><br></p>' ? '' : content;
            });
        }

        // Synchronisation éditeur privé
        if (this.quillPrivee && privateField) {
            this.quillPrivee.on('text-change', () => {
                const content = this.quillPrivee.root.innerHTML;
                privateField.value = content === '<p><br></p>' ? '' : content;
            });
        }

        // Synchronisation avant soumission du formulaire
        const form = document.getElementById('devis-form') || document.querySelector('form[name="devis"]') || document.querySelector('form');
        console.log('🔍 Recherche formulaire:', { found: !!form, id: form?.id, name: form?.name });

        if (form) {
            // Attacher au submit event du formulaire
            form.addEventListener('submit', (e) => {
                console.log('📝 Event submit détecté !');
                this.syncBeforeSubmit();
            });
            console.log('✅ Event listener submit attaché au formulaire');

            // AUSSI attacher aux boutons submit (car ils sont en dehors du form avec form="devis-form")
            const submitButtons = document.querySelectorAll('button[type="submit"][form="devis-form"]');
            console.log(`🔍 Trouvé ${submitButtons.length} boutons submit`);
            submitButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    console.log('🖱️ Click sur bouton submit détecté !');
                    this.syncBeforeSubmit();
                });
            });
            if (submitButtons.length > 0) {
                console.log('✅ Event listeners attachés aux boutons submit');
            }
        } else {
            console.error('❌ Formulaire non trouvé pour synchronisation WYSIWYG');
        }
    }

    /**
     * Force la synchronisation avant soumission
     */
    syncBeforeSubmit() {
        console.log('🔄 syncBeforeSubmit appelé');
        const publicField = document.getElementById(`note-publique-${this.prefix}`);
        const privateField = document.getElementById(`note-privee-${this.prefix}`);

        console.log('Champs trouvés:', { publicField: !!publicField, privateField: !!privateField });

        if (publicField && this.quillPublique) {
            const content = this.quillPublique.root.innerHTML;
            publicField.value = content === '<p><br></p>' ? '' : content;
            console.log('📝 Note publique synchronisée:', publicField.value.substring(0, 50));
        }

        if (privateField && this.quillPrivee) {
            const content = this.quillPrivee.root.innerHTML;
            privateField.value = content === '<p><br></p>' ? '' : content;
            console.log('🔒 Note privée synchronisée:', privateField.value.substring(0, 50));
        }
    }

    /**
     * Correction forcée des largeurs des dropdowns après initialisation (comme dans new.html.twig)
     */
    fixPickerWidths() {
        setTimeout(() => {
            // Pour la note publique
            const fontPickerPublique = document.querySelector(`#toolbar-note-publique-${this.prefix} .ql-font .ql-picker`);
            const sizePickerPublique = document.querySelector(`#toolbar-note-publique-${this.prefix} .ql-size .ql-picker`);

            if (fontPickerPublique) {
                fontPickerPublique.style.width = '220px';
                fontPickerPublique.style.minWidth = '220px';
                fontPickerPublique.style.maxWidth = '220px';
            }

            if (sizePickerPublique) {
                sizePickerPublique.style.width = '180px';
                sizePickerPublique.style.minWidth = '180px';
                sizePickerPublique.style.maxWidth = '180px';
            }

            // Pour la note privée
            const fontPickerPrivee = document.querySelector(`#toolbar-note-privee-${this.prefix} .ql-font .ql-picker`);
            const sizePickerPrivee = document.querySelector(`#toolbar-note-privee-${this.prefix} .ql-size .ql-picker`);

            if (fontPickerPrivee) {
                fontPickerPrivee.style.width = '220px';
                fontPickerPrivee.style.minWidth = '220px';
                fontPickerPrivee.style.maxWidth = '220px';
            }

            if (sizePickerPrivee) {
                sizePickerPrivee.style.width = '180px';
                sizePickerPrivee.style.minWidth = '180px';
                sizePickerPrivee.style.maxWidth = '180px';
            }
        }, 500);
    }

    /**
     * Détruit les éditeurs (utile pour le nettoyage)
     */
    destroy() {
        if (this.quillPublique) {
            this.quillPublique = null;
        }
        if (this.quillPrivee) {
            this.quillPrivee = null;
        }
        this.initialized = false;
    }

    /**
     * Met à jour le contenu d'un éditeur
     */
    setContent(type, content) {
        if (type === 'public' && this.quillPublique) {
            this.quillPublique.root.innerHTML = content || '';
        } else if (type === 'private' && this.quillPrivee) {
            this.quillPrivee.root.innerHTML = content || '';
        }
    }

    /**
     * Récupère le contenu d'un éditeur
     */
    getContent(type) {
        if (type === 'public' && this.quillPublique) {
            const content = this.quillPublique.root.innerHTML;
            return content === '<p><br></p>' ? '' : content;
        } else if (type === 'private' && this.quillPrivee) {
            const content = this.quillPrivee.root.innerHTML;
            return content === '<p><br></p>' ? '' : content;
        }
        return '';
    }
}

// Export global pour utilisation dans les templates
window.WysiwygNotesManager = WysiwygNotesManager;