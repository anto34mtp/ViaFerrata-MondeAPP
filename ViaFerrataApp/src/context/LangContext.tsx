import React, {createContext, useContext, useState, useCallback} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import fr from '../i18n/fr';
import en from '../i18n/en';
import de from '../i18n/de';
import es from '../i18n/es';

type Lang = 'fr' | 'en' | 'de' | 'es';

type Translations = typeof fr;

interface LangContextType {
  lang: Lang;
  t: Translations;
  setLang: (lang: Lang) => void;
}

const translations: Record<Lang, Translations> = {fr, en, de, es};

const LangContext = createContext<LangContextType>({
  lang: 'fr',
  t: fr,
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
      value={{lang, t: translations[lang], setLang}}>
      {children}
    </LangContext.Provider>
  );
};

export const useLang = () => useContext(LangContext);
