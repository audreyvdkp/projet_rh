import pandas as pd
import random

# Charger le dataset
dataset = pd.read_csv("dataset_recrutement_propre.csv")

# Niveaux possibles pour chaque langue
niveaux = {
    "Français": ["intermédiaire", "avancé", "courant"],
    "Anglais": ["débutant", "intermédiaire", "avancé", "courant"]
}


# Ajouter un niveau à chaque langue
def ajouter_niveaux(langues):
    langues_liste = [langue.strip() for langue in langues.split(";")]

    langues_avec_niveaux = []

    for langue in langues_liste:
        if langue in niveaux:
            niveau = random.choice(niveaux[langue])
            langues_avec_niveaux.append(
                f"{langue} ({niveau})"
            )
        else:
            langues_avec_niveaux.append(langue)

    return "; ".join(langues_avec_niveaux)


# Modifier les langues requises
dataset["langues_requises"] = dataset["langues_requises"].apply(
    ajouter_niveaux
)

# Modifier les langues du candidat
dataset["langues_candidat"] = dataset["langues_candidat"].apply(
    ajouter_niveaux
)

# Enregistrer le dataset corrigé
dataset.to_csv(
    "dataset_recrutement_langues.csv",
    index=False,
    encoding="utf-8-sig"
)

print("Dataset corrigé avec succès !")
print("\nAperçu :")
print(
    dataset[
        [
            "langues_requises",
            "langues_candidat"
        ]
    ].head()
)