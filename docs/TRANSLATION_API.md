# API Traduction - Documentation

## 🌐 Fonctionnalités

- **Extraction de texte intelligente** depuis PDF et images via plusieurs APIs
- **Traduction multilingue** (15+ langues supportées)
- **Fallback automatique** en cas d'échec d'une méthode
- **Sans installation requise** - APIs externes intégrées
- **Interface professionnelle** avec affichage côté-à-côté
- **Export** de la traduction en fichier texte
- **API REST** pour l'intégration

## 📋 Langues Supportées

- 🇬🇧 English (en)
- 🇫🇷 Français (fr)
- 🇪🇸 Español (es)
- 🇩🇪 Deutsch (de)
- 🇮🇹 Italiano (it)
- 🇵🇹 Português (pt)
- 🇷🇺 Русский (ru)
- 🇯🇵 日本語 (ja)
- 🇨🇳 中文 (zh)
- 🇸🇦 العربية (ar)
- 🇰🇷 한국어 (ko)
- 🇹🇷 Türkçe (tr)
- 🇳🇱 Nederlands (nl)
- 🇵🇱 Polski (pl)
- 🇻🇳 Tiếng Việt (vi)

## 🚀 Installation et Configuration

### ✅ Fonctionnement par défaut (Sans installation)

Le système fonctionne **immédiatement** avec :

**Pour les PDFs:**
1. CloudConvert API (gratuit - 25 conversions/jour)
2. PDF2Go API (gratuit)
3. Extraction brute (algorithme propriétaire)

**Pour les images:**
1. OCR.space API (gratuit)
2. Google Vision API (1000 req/mois gratuit)
3. Azure Computer Vision API (5000 req/mois gratuit)

### 🔧 Installation optionnelle pour meilleure qualité

#### Pour extraire du texte depuis les PDFs:

```bash
# Linux/Mac
sudo apt-get install poppler-utils  # Ubuntu/Debian
brew install poppler              # macOS

# Windows
# Télécharger depuis: https://github.com/oschwartz10612/poppler-windows
# Ou installer via Chocolatey:
choco install poppler
```

#### Pour extraire du texte depuis les images (OCR):

```bash
# Linux/Mac
sudo apt-get install tesseract-ocr    # Ubuntu/Debian
brew install tesseract                # macOS

# Windows
# Télécharger depuis: https://github.com/UB-Mannheim/tesseract/wiki
# Ou installer via Chocolatey:
choco install tesseract
```

### 📦 Dépendances PHP optionnelles

```bash
# Pour PDF Parser local (meilleure performance)
composer require smalot/pdfparser
```

### 📝 Configuration Symfony (.env)

```env
# .env (Optionnel - valeurs par défaut)
GOOGLE_TRANSLATE_API_KEY=          # Laisser vide = service gratuit (MyMemory)
AZURE_VISION_KEY=                   # Optionnel pour OCR Azure
CLOUDCONVERT_API_KEY=               # Optionnel pour CloudConvert premium
```

## 📚 Utilisation

### Interface Web

1. Allez à la page de détail d'un document (`/document/{id}`)
2. Scrollez jusqu'au panneau **"Traduction du Contenu"**
3. Sélectionnez la langue cible
4. Attendez l'extraction et la traduction
5. Copiez ou téléchargez la traduction

### API REST

#### Endpoint : POST `/api/translation/document/{id}/translate`

**Request:**
```bash
curl -X POST http://localhost:8000/api/translation/document/17/translate \
  -H "Content-Type: application/json" \
  -d '{"targetLanguage": "en"}'
```

**Response (Succès):**
```json
{
  "success": true,
  "original": {
    "text": "Texte extrait du document...",
    "charCount": 500,
    "wordCount": 75
  },
  "translated": {
    "text": "Document text extracted...",
    "language": "en",
    "charCount": 520,
    "wordCount": 78
  }
}
```

**Response (Erreur):**
```json
{
  "success": false,
  "message": "Description de l'erreur...",
  "suggestions": {
    "pdf_quality": "Le PDF doit être text-based",
    "image_quality": "L'image doit avoir bonne résolution",
    "file_format": "Formats supportés: PDF, JPG, PNG, GIF, WEBP"
  }
}
```

#### Endpoint : GET `/api/translation/languages`

**Response:**
```json
{
  "success": true,
  "languages": {
    "en": "English",
    "fr": "Français",
    "es": "Español",
    ...
  }
}
```

## 🛠️ Architecture de Fallback

### Extraction PDF (ordre de priorité)

```
1. pdftotext (système) [Meilleure qualité si installé]
   ↓
2. Smalot PDFParser (PHP)
   ↓
3. CloudConvert API (Gratuit)
   ↓
4. PDF2Go API (Gratuit)
   ↓
5. Extraction brute PDF (Algorithme propriétaire)
```

### OCR Images (ordre de priorité)

```
1. tesseract (système) [Meilleure qualité si installé]
   ↓
2. OCR.space API (Gratuit)
   ↓
3. Google Vision API (Gratuit 1000 req/mois)
   ↓
4. Azure Computer Vision API (Gratuit 5000 req/mois)
```

## ⚙️ Services Créés

### 1. TranslationService
- **Location**: `src/Service/TranslationService.php`
- **Responsabilité**: Traduction multilingue
- **APIs supportées**: MyMemory (gratuit), Google Translate (payant)

### 2. DocumentOCRService
- **Location**: `src/Service/DocumentOCRService.php`
- **Responsabilité**: Extraction de texte
- **Méthodes**: 
  - PDFs: pdftotext, PDFParser, CloudConvert, PDF2Go, brute
  - Images: Tesseract, OCR.space, Google Vision, Azure

### 3. TranslationController
- **Location**: `src/Controller/API/TranslationController.php`
- **Endpoints**:
  - `POST /api/translation/document/{id}/translate`
  - `GET /api/translation/languages`

## 📊 Limites et Quotas

### Gratuit

| Service | Limite | Réinitialisation |
|---------|--------|-----------------|
| MyMemory API | Illimité | - |
| OCR.space | Illimité | - |
| CloudConvert | 25/jour | Chaque jour |
| Google Vision | 1000/mois | Chaque mois |
| Azure Vision | 5000/mois | Chaque mois |
| Caractères/traduction | 5000 chars | Par requête |

### Payant (Premium)

- CloudConvert: $0.005 par conversion
- Google Translate: $15-20 par million chars
- Azure Computer Vision: $1 par 1000 images

## 🐛 Dépannage

### "Impossible d'extraire le texte du document"

**1. Vérifiez le fichier:**
```bash
# PDF: Vérifier qu'il n'est pas scané
file document.pdf
# Devrait afficher: "PDF document, version X.Y"

# Image: Vérifier la résolution
identify document.jpg
# Devrait afficher: width x height >= 300x300
```

**2. Testez localement:**
```bash
# Si pdftotext est installé
pdftotext document.pdf output.txt

# Si tesseract est installé
tesseract document.jpg output
```

**3. Vérifiez les logs:**
```bash
tail -f var/log/dev.log | grep -i "translation\|ocr\|extract"
```

### Les APIs externes ne répondent pas

**Solutions:**
1. Vérifier votre connexion Internet
2. Attendre quelques minutes (API rate-limited)
3. Installer les outils locaux pour plus de fiabilité
4. Upgrader vers les APIs payantes si besoin

### Performance lente

**Recommandations:**
1. Installer `pdftotext` et `tesseract` localement
2. Réduire la taille des fichiers
3. Utiliser les APIs payantes pour une meilleure performance

## 🔒 Sécurité

- ✅ Les fichiers sont traitées côté serveur
- ✅ Les communications API utilisent HTTPS
- ✅ Validation des paramètres d'entrée
- ✅ Pas de stockage des traductions
- ⚠️ Les APIs externes peuvent conserver temporairement les fichiers

## 🚀 Améliorations Futures

- [ ] Cache des traductions
- [ ] Historique par utilisateur
- [ ] Support Word/Excel/PowerPoint
- [ ] Interface Admin pour gérer les clés API
- [ ] Webhooks pour traductions en arrière-plan
- [ ] Extraction multilingue batch

## 📝 Licence

Propriétaire - FinTrack

## 🤝 Support

Pour toute question ou problème, contacter l'équipe de développement.

