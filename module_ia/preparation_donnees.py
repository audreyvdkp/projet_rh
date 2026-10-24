import pandas as pd

# Charger le dataset
dataset = pd.read_csv("dataset_recrutement_langues.csv")

# Afficher les premières lignes
print(dataset.head())

# Afficher les informations générales
print("\nInformations sur le dataset :")
print(dataset.info())

# Fonction pour calculer le taux de correspondance des compétences
def calculer_correspondance(liste_requise, liste_candidat):
    requises = [x.strip().lower() for x in liste_requise.split(";")]
    candidat = [x.strip().lower() for x in liste_candidat.split(";")]

    if len(requises) == 0:
        return 0

    correspondances = sum(
        1 for competence in requises
        if competence in candidat
    )

    return correspondances / len(requises)


# Créer la nouvelle colonne de correspondance
dataset["taux_competences"] = dataset.apply(
    lambda ligne: calculer_correspondance(
        ligne["competences_requises"],
        ligne["competences_candidat"]
    ),
    axis=1
)

# Afficher un aperçu
print("\nCorrespondance des compétences :")
print(
    dataset[
        [
            "competences_requises",
            "competences_candidat",
            "taux_competences"
        ]
    ].head()
)

# Fonction pour calculer la correspondance de l'expérience
def calculer_correspondance_experience(experience_requise, experience_candidat):

    if experience_requise == 0:
        return 1

    taux = experience_candidat / experience_requise

    # Le taux ne doit pas dépasser 1
    return min(taux, 1)


# Créer la colonne de correspondance de l'expérience
dataset["taux_experience"] = dataset.apply(
    lambda ligne: calculer_correspondance_experience(
        ligne["experience_requise"],
        ligne["experience_candidat"]
    ),
    axis=1
)


# Afficher un aperçu
print("\nCorrespondance de l'expérience :")
print(
    dataset[
        [
            "experience_requise",
            "experience_candidat",
            "taux_experience"
        ]
    ].head()
)

# Fonction pour calculer la correspondance du diplôme
def calculer_correspondance_diplome(diplome_requis, diplome_candidat):

    niveaux = {
        "Bac": 1,
        "Licence": 2,
        "Master": 3
    }

    niveau_requis = niveaux.get(diplome_requis, 0)
    niveau_candidat = niveaux.get(diplome_candidat, 0)

    if niveau_requis == 0:
        return 0

    # Calcul du rapport entre le niveau du candidat et le niveau requis
    taux = niveau_candidat / niveau_requis

    # Limiter le bonus à 1,2 maximum
    return min(taux, 1.2)

# Créer la colonne de correspondance du diplôme
dataset["taux_diplome"] = dataset.apply(
    lambda ligne: calculer_correspondance_diplome(
        ligne["diplome_requis"],
        ligne["diplome_candidat"]
    ),
    axis=1
)


# Afficher un aperçu
print("\nCorrespondance du diplôme :")
print(
    dataset[
        [
            "diplome_requis",
            "diplome_candidat",
            "taux_diplome"
        ]
    ].head()
)

# Correspondance des langues avec prise en compte du niveau
def calculer_correspondance_langues(langues_requises, langues_candidat):

    niveaux = {
        "débutant": 1,
        "intermédiaire": 2,
        "avancé": 3,
        "courant": 4
    }

    def convertir_langues(texte):
        resultat = {}

        langues = texte.split(";")

        for element in langues:
            element = element.strip()

            if "(" in element and ")" in element:
                langue, niveau = element.split("(")
                langue = langue.strip().lower()
                niveau = niveau.replace(")", "").strip().lower()

                resultat[langue] = niveaux.get(niveau, 0)

        return resultat

    requises = convertir_langues(langues_requises)
    candidat = convertir_langues(langues_candidat)

    if len(requises) == 0:
        return 0

    scores = []

    for langue, niveau_requis in requises.items():

        if langue in candidat:
            niveau_candidat = candidat[langue]

            taux = niveau_candidat / niveau_requis

            # Bonus maximum de 20 % si le niveau dépasse l'exigence
            taux = min(taux, 1)

            scores.append(taux)

        else:
            scores.append(0)

    return sum(scores) / len(scores)


# Créer la colonne taux_langues
dataset["taux_langues"] = dataset.apply(
    lambda ligne: calculer_correspondance_langues(
        ligne["langues_requises"],
        ligne["langues_candidat"]
    ),
    axis=1
)

print("\nCorrespondance des langues :")
print(
    dataset[
        [
            "langues_requises",
            "langues_candidat",
            "taux_langues"
        ]
    ].head()
)

print("\nDataset après préparation :")

print(
    dataset[
        [
            "taux_competences",
            "taux_experience",
            "taux_diplome",
            "taux_langues",
            "score_compatibilite"
        ]
    ].head()
)

# Vérifier la relation entre les taux et le score final

colonnes = [
    "taux_competences",
    "taux_experience",
    "taux_diplome",
    "taux_langues",
    "score_compatibilite"
]

print("\nVérification de la cohérence des données :")
print(dataset[colonnes].corr())

# Générer des pondérations réalistes
import random
ponderations = []

for _ in range(len(dataset)):
    reste = 100

    # Chaque critère reçoit au minimum 5 %
    poids_competences = random.randint(5, reste - 15)
    reste -= poids_competences

    poids_experience = random.randint(5, reste - 10)
    reste -= poids_experience

    poids_diplome = random.randint(5, reste - 5)
    reste -= poids_diplome

    poids_langues = reste

    ponderations.append([
        poids_competences / 100,
        poids_experience / 100,
        poids_diplome / 100,
        poids_langues / 100
    ])

# Ajouter les pondérations au dataset
dataset["poids_competences"] = [p[0] for p in ponderations]
dataset["poids_experience"] = [p[1] for p in ponderations]
dataset["poids_diplome"] = [p[2] for p in ponderations]
dataset["poids_langues"] = [p[3] for p in ponderations]


# Calculer le score avec les pondérations de chaque ligne
dataset["score_compatibilite"] = (
    dataset["taux_competences"] * dataset["poids_competences"]
    + dataset["taux_experience"] * dataset["poids_experience"]
    + dataset["taux_diplome"] * dataset["poids_diplome"]
    + dataset["taux_langues"] * dataset["poids_langues"]
) * 100

# Arrondir le score à deux décimales
dataset["score_compatibilite"] = dataset["score_compatibilite"].round(2)

print("\nNouveaux scores de compatibilité :")
print(
    dataset[
        [
            "taux_competences",
            "taux_experience",
            "taux_diplome",
            "taux_langues",
            "score_compatibilite"
        ]
    ].head()
)

# Enregistrer le dataset préparé
dataset.to_csv(
    "dataset_prepare.csv",
    index=False,
    encoding="utf-8-sig"
)

print("\nDataset préparé enregistré avec succès !")