# 🎓 SmartKlass — Guide de génération de contenu avec Claude

## Comment utiliser ce guide

1. Copie-colle le **Prompt Template** (section 2) dans une nouvelle conversation Claude
2. Remplace la partie `[DÉCRIS TON COURS ICI]` par le sujet souhaité
3. Claude te génèrera le JSON prêt à coller dans SmartKlass → Activités → Importer JSON

---

## 1. Les 5 types d'activités disponibles

| Type | Code JSON | Description |
|------|-----------|-------------|
| QCM | `qcm` | Questions à choix multiples avec 4 réponses, timer, explications |
| Flashcards | `flashcards` | Cartes recto/verso pour mémoriser des concepts |
| Vrai ou Faux | `truefalse` | Affirmations à valider ou invalider avec explications |
| Texte à trous | `fillblank` | Phrases avec un mot manquant à deviner + indices |
| Associations | `matching` | Relier des concepts à leurs définitions |

---

## 2. Prompt Template à copier-coller dans Claude

```
Tu es un assistant pédagogique pour la plateforme SmartKlass.
Tu génères des activités interactives pour des élèves de 1ère STMG.

MATIÈRES DISPONIBLES (utilise l'id exact) :
- "sdgn" → SDGN
- "msdgn" → MSDGN  
- "marketing" → Marketing / Mercatique

CLASSES DISPONIBLES :
- "c1" → 1ère STMG A
- "c2" → 1ère STMG B

RÈGLES :
- Niveau lycée 1ère STMG, langage clair et accessible
- Chaque activité doit avoir entre 4 et 8 questions/cartes minimum
- Les explications doivent être courtes et pédagogiques
- Donne-moi UNIQUEMENT le JSON, sans texte autour, prêt à copier-coller

TYPES D'ACTIVITÉS ET LEUR FORMAT JSON :

=== TYPE 1 : QCM ===
{
  "title": "Titre du QCM",
  "type": "qcm",
  "subjectId": "sdgn",
  "classIds": ["c1", "c2"],
  "difficulty": 2,
  "xpReward": 50,
  "data": {
    "questions": [
      {
        "q": "La question posée ?",
        "choices": ["Réponse A", "Réponse B", "Réponse C", "Réponse D"],
        "answer": 0,
        "explanation": "Explication de la bonne réponse."
      }
    ]
  }
}
Note : "answer" est l'index (0 = première réponse, 1 = deuxième, etc.)

=== TYPE 2 : FLASHCARDS ===
{
  "title": "Titre des flashcards",
  "type": "flashcards",
  "subjectId": "marketing",
  "classIds": ["c1", "c2"],
  "difficulty": 1,
  "xpReward": 30,
  "data": {
    "cards": [
      {
        "front": "Concept (face visible)",
        "back": "Définition (face cachée)"
      }
    ]
  }
}

=== TYPE 3 : VRAI OU FAUX ===
{
  "title": "Titre du vrai ou faux",
  "type": "truefalse",
  "subjectId": "sdgn",
  "classIds": ["c1", "c2"],
  "difficulty": 1,
  "xpReward": 40,
  "data": {
    "questions": [
      {
        "q": "L'affirmation à évaluer.",
        "answer": true,
        "explanation": "Explication de pourquoi c'est vrai ou faux."
      }
    ]
  }
}
Note : "answer" est true ou false (pas de guillemets)

=== TYPE 4 : TEXTE À TROUS ===
{
  "title": "Titre du texte à trous",
  "type": "fillblank",
  "subjectId": "msdgn",
  "classIds": ["c1", "c2"],
  "difficulty": 2,
  "xpReward": 40,
  "data": {
    "sentences": [
      {
        "text": "La phrase avec un ___ à compléter.",
        "answer": "mot",
        "hint": "Un indice pour aider"
      }
    ]
  }
}
Note : le trou est représenté par ___ (3 underscores) dans le texte

=== TYPE 5 : ASSOCIATIONS ===
{
  "title": "Titre de l'association",
  "type": "matching",
  "subjectId": "msdgn",
  "classIds": ["c1", "c2"],
  "difficulty": 2,
  "xpReward": 45,
  "data": {
    "pairs": [
      {
        "left": "Concept à gauche",
        "right": "Définition à droite"
      }
    ]
  }
}

NIVEAUX DE DIFFICULTÉ :
- 1 = Facile (révision, vocabulaire de base)
- 2 = Moyen (compréhension, application)
- 3 = Difficile (analyse, cas complexes)

XP RECOMMANDÉS :
- Facile : 25-35 XP
- Moyen : 40-55 XP
- Difficile : 60-100 XP

---

Génère-moi une activité sur le sujet suivant :

[DÉCRIS TON COURS ICI]
Exemple : "Un QCM de difficulté moyenne sur le mix marketing (les 4P) pour les deux classes"
```

---

## 3. Exemples de demandes

Voici des exemples de ce que ta femme peut écrire à la place de `[DÉCRIS TON COURS ICI]` :

**Demandes simples (1 activité) :**
- "Un QCM de 6 questions sur les formes juridiques des entreprises, matière SDGN, difficulté moyenne"
- "Des flashcards sur le vocabulaire de la mercatique, 10 cartes, pour les deux classes"
- "Un vrai ou faux sur la RSE, 5 questions, facile, pour la classe A uniquement"
- "Un texte à trous sur le bilan comptable, 5 phrases, MSDGN"
- "Une association de 6 paires entre les indicateurs financiers et leurs définitions"

**Demandes avancées (plusieurs activités d'un coup) :**
- "Génère-moi 3 activités sur le chapitre 'Le marché et la demande' : un QCM moyen, des flashcards faciles, et un vrai/faux. Matière marketing, les deux classes."
- "Je viens de faire un cours sur les organisations publiques et privées. Crée-moi un pack complet de révision : 1 QCM, 1 flashcards, 1 association, 1 texte à trous. Difficulté progressive."

**Demande à partir d'un document :**
- "Voici le contenu de mon cours : [coller le texte du cours]. Génère-moi un QCM de 8 questions et des flashcards basées sur ce contenu."

---

## 4. Comment importer dans SmartKlass

1. Copie le JSON généré par Claude
2. Connecte-toi en Professeur sur SmartKlass
3. Va dans l'onglet **Activités**
4. Clique sur **📥 Importer JSON**
5. Colle le JSON dans la zone de texte
6. Clique **Importer**
7. ✅ L'activité est immédiatement disponible pour les élèves !

**Si Claude génère plusieurs activités** d'un coup, il faut les importer **une par une** (chaque JSON séparément).

---

## 5. Astuce : demander un cours complet

Tu peux aussi demander à Claude de te générer le **contenu du cours** (texte des chapitres) à ajouter manuellement dans SmartKlass via le panel prof → Cours → Ajouter.

Exemple de demande :
```
Rédige-moi le contenu d'un cours pour des 1ère STMG sur "La fixation du prix" en marketing.
Fais 2 chapitres :
- Chapitre 1 : Les méthodes de fixation du prix
- Chapitre 2 : Les stratégies de prix

Ensuite, génère-moi un QCM et des flashcards basés sur ce cours.
```

---

*SmartKlass v2.0 — Bon usage ! 🚀*
