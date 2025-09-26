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
        optionsNotePublique.modules.toolbar.container = '#toolbar-note-publique';
        optionsNotePublique.placeholder = '';

        this.quillPublique = new Quill('#editor-note-publique', optionsNotePublique);

        // Initialisation de l'éditeur pour note privée
        const optionsNotePrivee = { ...quillOptions };
        optionsNotePrivee.modules.toolbar.container = '#toolbar-note-privee';
        optionsNotePrivee.placeholder = '';

        this.quillPrivee = new Quill('#editor-note-privee', optionsNotePrivee);

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

        if (publicField && publicField.value && this.quillPublique) {
            this.quillPublique.root.innerHTML = publicField.value;
        }

        if (privateField && privateField.value && this.quillPrivee) {
            this.quillPrivee.root.innerHTML = privateField.value;
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
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', () => {
                this.syncBeforeSubmit();
            });
        }
    }

    /**
     * Force la synchronisation avant soumission
     */
    syncBeforeSubmit() {
        const publicField = document.getElementById(`note-publique-${this.prefix}`);
        const privateField = document.getElementById(`note-privee-${this.prefix}`);

        if (publicField && this.quillPublique) {
            const content = this.quillPublique.root.innerHTML;
            publicField.value = content === '<p><br></p>' ? '' : content;
        }

        if (privateField && this.quillPrivee) {
            const content = this.quillPrivee.root.innerHTML;
            privateField.value = content === '<p><br></p>' ? '' : content;
        }
    }

    /**
     * Correction forcée des largeurs des dropdowns après initialisation (comme dans new.html.twig)
     */
    fixPickerWidths() {
        setTimeout(() => {
            // Pour la note publique
            const fontPickerPublique = document.querySelector('#toolbar-note-publique .ql-font .ql-picker');
            const sizePickerPublique = document.querySelector('#toolbar-note-publique .ql-size .ql-picker');

            if (fontPickerPublique) {
                fontPickerPublique.style.width = '180px';
                fontPickerPublique.style.minWidth = '180px';
                fontPickerPublique.style.maxWidth = '180px';
            }

            if (sizePickerPublique) {
                sizePickerPublique.style.width = '140px';
                sizePickerPublique.style.minWidth = '140px';
                sizePickerPublique.style.maxWidth = '140px';
            }

            // Pour la note privée
            const fontPickerPrivee = document.querySelector('#toolbar-note-privee .ql-font .ql-picker');
            const sizePickerPrivee = document.querySelector('#toolbar-note-privee .ql-size .ql-picker');

            if (fontPickerPrivee) {
                fontPickerPrivee.style.width = '180px';
                fontPickerPrivee.style.minWidth = '180px';
                fontPickerPrivee.style.maxWidth = '180px';
            }

            if (sizePickerPrivee) {
                sizePickerPrivee.style.width = '140px';
                sizePickerPrivee.style.minWidth = '140px';
                sizePickerPrivee.style.maxWidth = '140px';
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