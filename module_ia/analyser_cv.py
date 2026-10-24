import sys
import joblib
import pandas as pd
import pdfplumber
import re
import os

DOSSIER = os.path.dirname(os.path.abspath(__file__))

def extraire_texte_pdf(chemin):
    texte = ""
    try:
        with pdfplumber.open(chemin) as pdf:
            for page in pdf.pages:
                t = page.extract_text()
                if t: texte += t + "\n"
    except:
        pass
    return texte

def analyser(texte_cv, texte_offre):
    cv_bas = texte_cv.lower()
    offre_bas = texte_offre.lower()

    skills_liste = [
        "python", "java", "javascript", "php", "html", "css", "sql", "react", "angular", "vue",
        "photoshop", "illustrator", "indesign", "figma", "canva", "sketch",
        "django", "flask", "node", "git", "docker", "aws", "azure",
        "excel", "word", "powerpoint", "sap", "oracle",
        "marketing", "vente", "gestion", "communication", "design", "graphisme",
        "machine learning", "data", "seo", "sea", "wordpress", "fullstack", "full stack"
    ]
    
    competences_offre = [s for s in skills_liste if s in offre_bas]
    competences_cv = [s for s in skills_liste if s in cv_bas]
    
    if len(competences_offre) == 0:
        taux_comp = 0.5
    else:
        correspondances = sum(1 for c in competences_offre if c in competences_cv)
        taux_comp = correspondances / len(competences_offre)

    # Expérience
    taux_exp = 0.5
    match_annees = re.search(r'(\d{1,2})\s*(?:ans|années|an)', cv_bas)
    if match_annees:
        ans = int(match_annees.group(1))
        taux_exp = min(ans / 5.0, 1.0)
    else:
        match_dates = re.findall(r'(20\d{2})\s*[-–àa]\s*(20\d{2}|présent|actuel)', cv_bas)
        if match_dates:
            annees_exp = sum((2024 if f in ['présent', 'actuel'] else int(f)) - int(d) for d, f in match_dates)
            taux_exp = min(annees_exp / 5.0, 1.0)

    # Diplôme
    taux_diplome = 0.5
    if "doctorat" in cv_bas or "phd" in cv_bas: taux_diplome = 1.0
    elif "master" in cv_bas or "bac+5" in cv_bas: taux_diplome = 0.9
    elif "licence" in cv_bas or "bac+3" in cv_bas: taux_diplome = 0.7
    elif "bts" in cv_bas or "dut" in cv_bas or "bac+2" in cv_bas: taux_diplome = 0.5
    elif "bac" in cv_bas: taux_diplome = 0.3

    # Langues
    taux_langues = 0.5
    langues_trouvees = 0
    langues_total = 0
    for langue in ["anglais", "français", "espagnol", "allemand", "arabe"]:
        if langue in offre_bas:
            langues_total += 1
            if langue in cv_bas:
                if "courant" in cv_bas or "natif" in cv_bas or "bilingue" in cv_bas:
                    langues_trouvees += 1.0
                elif "avancé" in cv_bas or "professionnel" in cv_bas:
                    langues_trouvees += 0.8
                elif "intermédiaire" in cv_bas:
                    langues_trouvees += 0.5
                else:
                    langues_trouvees += 0.3
    if langues_total > 0:
        taux_langues = langues_trouvees / langues_total

    p_comp, p_exp, p_dipl, p_lang = 0.4, 0.3, 0.2, 0.1

    try:
        modele = joblib.load(os.path.join(DOSSIER, 'modele_compatibilite.pkl'))
        data = pd.DataFrame({
            'taux_competences': [taux_comp],
            'taux_experience': [taux_exp],
            'taux_diplome': [taux_diplome],
            'taux_langues': [taux_langues],
            'poids_competences': [p_comp],
            'poids_experience': [p_exp],
            'poids_diplome': [p_dipl],
            'poids_langues': [p_lang]
        })
        score = float(modele.predict(data)[0])
    except:
        score = (taux_comp * p_comp + taux_exp * p_exp + taux_diplome * p_dipl + taux_langues * p_lang) * 100

    return round(score, 2)

if __name__ == "__main__":
    if len(sys.argv) < 4:
        # Écrire 0 dans le fichier de sortie
        if len(sys.argv) >= 4:
            with open(sys.argv[3], 'w') as f:
                f.write("0")
        else:
            print("0")
        sys.exit(1)
    
    chemin_cv = sys.argv[1]
    chemin_offre = sys.argv[2]
    fichier_sortie = sys.argv[3] if len(sys.argv) >= 4 else None
    
    texte_cv = extraire_texte_pdf(chemin_cv)
    texte_offre = ""
    if os.path.exists(chemin_offre):
        with open(chemin_offre, 'r', encoding='utf-8') as f:
            texte_offre = f.read()

    if not texte_cv:
        score = 10
    else:
        score = analyser(texte_cv, texte_offre)

    # Écrire le score dans le fichier (méthode fiable)
    if fichier_sortie:
        with open(fichier_sortie, 'w') as f:
            f.write(str(score))
    
    # Debug log
    with open(os.path.join(DOSSIER, 'debug.log'), 'w', encoding='utf-8') as f:
        f.write(f"Score: {score}\n")
        f.write(f"CV texte (500 premiers chars): {texte_cv[:500]}\n")
        f.write(f"Offre texte (500 premiers chars): {texte_offre[:500]}\n")