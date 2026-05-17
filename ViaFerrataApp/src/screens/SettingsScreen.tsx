import React from 'react';
import {
  View, Text, TouchableOpacity, StyleSheet, ScrollView, Alert,
} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {useAuth} from '../context/AuthContext';
import {useLang} from '../context/LangContext';
import {RootStackParamList} from '../navigation/AppNavigator';

const PRIMARY = '#2E7D32';
type Nav = NativeStackNavigationProp<RootStackParamList>;

const LANGUAGES = [
  {code: 'fr', label: 'Français', flag: '🇫🇷'},
  {code: 'en', label: 'English', flag: '🇬🇧'},
  {code: 'de', label: 'Deutsch', flag: '🇩🇪'},
  {code: 'es', label: 'Español', flag: '🇪🇸'},
];

export default function SettingsScreen() {
  const {t, lang, setLang} = useLang();
  const {user, logout} = useAuth();
  const navigation = useNavigation<Nav>();

  const handleLogout = () => {
    Alert.alert(t('auth.logout'), t('auth.confirmLogout'), [
      {text: t('common.cancel'), style: 'cancel'},
      {
        text: t('auth.logout'), style: 'destructive',
        onPress: async () => { await logout(); },
      },
    ]);
  };

  return (
    <ScrollView style={styles.container}>
      {/* User section */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('settings.account')}</Text>
        {user ? (
          <View style={styles.userCard}>
            <View style={styles.avatar}>
              <Text style={styles.avatarText}>{user.username.charAt(0).toUpperCase()}</Text>
            </View>
            <View style={styles.userInfo}>
              <Text style={styles.username}>{user.username}</Text>
              <Text style={styles.email}>{user.email}</Text>
              {user.role && user.role !== 'member' && (
                <View style={styles.roleBadge}>
                  <Text style={styles.roleText}>{user.role}</Text>
                </View>
              )}
            </View>
          </View>
        ) : (
          <View style={styles.authButtons}>
            <TouchableOpacity style={styles.loginBtn} onPress={() => navigation.navigate('Login')}>
              <Text style={styles.loginBtnText}>{t('auth.login')}</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.registerBtn} onPress={() => navigation.navigate('Register')}>
              <Text style={styles.registerBtnText}>{t('auth.register')}</Text>
            </TouchableOpacity>
          </View>
        )}
      </View>

      {/* Language section */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('settings.language')}</Text>
        {LANGUAGES.map(l => (
          <TouchableOpacity
            key={l.code}
            style={[styles.langOption, lang === l.code && styles.langSelected]}
            onPress={() => setLang(l.code as any)}>
            <Text style={styles.langFlag}>{l.flag}</Text>
            <Text style={[styles.langLabel, lang === l.code && styles.langLabelSelected]}>
              {l.label}
            </Text>
            {lang === l.code && <Text style={styles.checkmark}>✓</Text>}
          </TouchableOpacity>
        ))}
      </View>

      {/* Navigation section */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('settings.explore')}</Text>
        <MenuItem icon="🏔" label={t('nav.catalog')} onPress={() => navigation.navigate('Catalog' as any)} />
        <MenuItem icon="🗺" label={t('nav.map')} onPress={() => navigation.navigate('Map' as any)} />
        {user && (
          <>
            <MenuItem icon="⭐" label={t('nav.favorites')} onPress={() => navigation.navigate('Favorites')} />
            <MenuItem icon="📖" label={t('nav.logbook')} onPress={() => navigation.navigate('Logbook')} />
            <MenuItem icon="🚀" label={t('nav.trips')} onPress={() => navigation.navigate('RoadTrips')} />
          </>
        )}
        <MenuItem icon="➕" label={t('submit.title')} onPress={() => navigation.navigate('SubmitVia')} />
      </View>

      {/* Logout */}
      {user && (
        <View style={styles.section}>
          <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout}>
            <Text style={styles.logoutBtnText}>🚪 {t('auth.logout')}</Text>
          </TouchableOpacity>
        </View>
      )}

      <View style={styles.footer}>
        <Text style={styles.footerText}>ViaFerrata-Monde.fr</Text>
        <Text style={styles.footerVersion}>v1.0.0</Text>
      </View>
    </ScrollView>
  );
}

function MenuItem({icon, label, onPress}: {icon: string; label: string; onPress: () => void}) {
  return (
    <TouchableOpacity style={styles.menuItem} onPress={onPress}>
      <Text style={styles.menuIcon}>{icon}</Text>
      <Text style={styles.menuLabel}>{label}</Text>
      <Text style={styles.menuArrow}>›</Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#f5f5f5'},
  section: {backgroundColor: '#fff', marginTop: 16, marginHorizontal: 16, borderRadius: 12, padding: 16, elevation: 1},
  sectionTitle: {fontSize: 12, fontWeight: '700', color: '#888', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 12},
  userCard: {flexDirection: 'row', alignItems: 'center', gap: 12},
  avatar: {width: 50, height: 50, borderRadius: 25, backgroundColor: PRIMARY, justifyContent: 'center', alignItems: 'center'},
  avatarText: {color: '#fff', fontSize: 20, fontWeight: 'bold'},
  userInfo: {flex: 1},
  username: {fontSize: 17, fontWeight: '600', color: '#333'},
  email: {fontSize: 13, color: '#888', marginTop: 2},
  roleBadge: {backgroundColor: '#E8F5E9', borderRadius: 4, paddingHorizontal: 6, paddingVertical: 2, marginTop: 4, alignSelf: 'flex-start'},
  roleText: {color: PRIMARY, fontSize: 11, fontWeight: '600', textTransform: 'capitalize'},
  authButtons: {gap: 10},
  loginBtn: {backgroundColor: PRIMARY, borderRadius: 8, padding: 12, alignItems: 'center'},
  loginBtnText: {color: '#fff', fontWeight: 'bold', fontSize: 15},
  registerBtn: {borderWidth: 1, borderColor: PRIMARY, borderRadius: 8, padding: 12, alignItems: 'center'},
  registerBtnText: {color: PRIMARY, fontWeight: 'bold', fontSize: 15},
  langOption: {flexDirection: 'row', alignItems: 'center', padding: 12, borderRadius: 8, marginBottom: 4},
  langSelected: {backgroundColor: '#E8F5E9'},
  langFlag: {fontSize: 22, marginRight: 12},
  langLabel: {flex: 1, fontSize: 16, color: '#333'},
  langLabelSelected: {color: PRIMARY, fontWeight: '600'},
  checkmark: {color: PRIMARY, fontSize: 18, fontWeight: 'bold'},
  menuItem: {flexDirection: 'row', alignItems: 'center', paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: '#f0f0f0'},
  menuIcon: {fontSize: 20, marginRight: 12},
  menuLabel: {flex: 1, fontSize: 15, color: '#333'},
  menuArrow: {fontSize: 20, color: '#ccc'},
  logoutBtn: {borderWidth: 1, borderColor: '#f44336', borderRadius: 8, padding: 14, alignItems: 'center'},
  logoutBtnText: {color: '#f44336', fontWeight: 'bold', fontSize: 15},
  footer: {alignItems: 'center', padding: 24},
  footerText: {color: '#aaa', fontSize: 14, fontWeight: '600'},
  footerVersion: {color: '#ccc', fontSize: 12, marginTop: 2},
});
