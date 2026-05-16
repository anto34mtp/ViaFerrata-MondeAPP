import React, {createContext, useContext, useState, useCallback} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import fr from '../i18n/fr';
import en from '../i18n/en';
import de from '../i18n/de';
import es from '../i18n/es';

type Lang = 'fr' | 'en' | 'de' | 'es';

type Translations = typeof fr;

// resolve('nav.home', translations) → 'Accueil'
function resolve(path: string, obj: any): string {
  return path.split('.').reduce((acc, key) => (acc && acc[key] !== undefined ? acc[key] : path), obj);
}

interface LangContextType {
  lang: Lang;
  t: ((key: string) => string) & Translations;
  setLang: (lang: Lang) => void;
}

const translations: Record<Lang, Translations> = {fr, en, de, es};

function makeT(dict: Translations): ((key: string) => string) & Translations {
  const fn = (key: string) => resolve(key, dict);
  return Object.assign(fn, dict) as any;
}

const LangContext = createContext<LangContextType>({
  lang: 'fr',
  t: makeT(fr),
  setLang: () => {},
});

export const LangProvider: React.FC<{children: React.ReactNode}> = ({
  children,
}) => {
  const [lang, setLangState] = useState<Lang>('fr');

  React.useEffect(() => {
    AsyncStorage.getItem('@viaferrata_lang').then(saved => {
      if (saved && ['fr', 'en', 'de', 'es'].includes(saved)) {
        setLangState(saved as Lang);
      }
    });
  }, []);

  const setLang = useCallback((newLang: Lang) => {
    setLangState(newLang);
    AsyncStorage.setItem('@viaferrata_lang', newLang);
  }, []);

  return (
    <LangContext.Provider
      value={{lang, t: makeT(translations[lang]), setLang}}>
      {children}
    </LangContext.Provider>
  );
};

export const useLang = () => useContext(LangContext);
