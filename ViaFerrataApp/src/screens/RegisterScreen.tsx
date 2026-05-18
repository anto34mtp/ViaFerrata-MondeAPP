import React, {useState} from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  ActivityIndicator,
  Alert,
} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import {register as apiRegister} from '../api/client';
import {useAuth} from '../context/AuthContext';
import {useLang} from '../context/LangContext';
import TurnstileWidget from '../components/TurnstileWidget';

const RegisterScreen: React.FC = () => {
  const {t} = useLang();
  const {login} = useAuth();
  const navigation = useNavigation<any>();
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [turnstileToken, setTurnstileToken] = useState<string | null>(null);

  const handleRegister = async () => {
    if (!username.trim() || !email.trim() || !password.trim()) return;
    setLoading(true);
    try {
      const res = await apiRegister({
        username: username.trim(),
        email: email.trim(),
        password,
        turnstile_token: turnstileToken || undefined,
      });
      await login(res.data.token, res.data.user);
      navigation.goBack();
    } catch (e: any) {
      Alert.alert(t.common.error, t.auth.registerError);
    } finally {
      setLoading(false);
    }
  };

  const isValid = username.trim() && email.trim() && password.trim() && !!turnstileToken;

  return (
    <KeyboardAvoidingView
      style={styles.wrapper}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
      <ScrollView contentContainerStyle={styles.container}>
        <View style={styles.logoContainer}>
          <Text style={styles.logoEmoji}>🏔️</Text>
          <Text style={styles.logoTitle}>Via Ferrata Monde</Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.title}>{t.auth.registerTitle}</Text>

          <Text style={styles.label}>{t.auth.username}</Text>
          <TextInput
            style={styles.input}
            placeholder={t.auth.usernamePlaceholder}
            placeholderTextColor="#999"
            value={username}
            onChangeText={setUsername}
            autoCapitalize="none"
            returnKeyType="next"
          />

          <Text style={styles.label}>{t.auth.email}</Text>
          <TextInput
            style={styles.input}
            placeholder={t.auth.emailPlaceholder}
            placeholderTextColor="#999"
            value={email}
            onChangeText={setEmail}
            autoCapitalize="none"
            keyboardType="email-address"
            returnKeyType="next"
          />

          <Text style={styles.label}>{t.auth.password}</Text>
          <TextInput
            style={styles.input}
            placeholder={t.auth.passwordPlaceholder}
            placeholderTextColor="#999"
            value={password}
            onChangeText={setPassword}
            secureTextEntry
            returnKeyType="done"
            onSubmitEditing={handleRegister}
          />

          <TurnstileWidget onVerify={token => setTurnstileToken(token)} />

          <TouchableOpacity
            style={[styles.button, (!isValid || loading) && styles.buttonDisabled]}
            onPress={handleRegister}
            disabled={!isValid || loading}>
            {loading ? (
              <ActivityIndicator color="#FFFFFF" />
            ) : (
              <Text style={styles.buttonText}>{t.auth.registerButton}</Text>
            )}
          </TouchableOpacity>

          <View style={styles.switchRow}>
            <Text style={styles.switchText}>{t.auth.alreadyAccount} </Text>
            <TouchableOpacity onPress={() => navigation.replace('Login')}>
              <Text style={styles.switchLink}>{t.auth.loginLink}</Text>
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  wrapper: {flex: 1, backgroundColor: '#2E7D32'},
  container: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: 20,
  },
  logoContainer: {
    alignItems: 'center',
    marginBottom: 28,
  },
  logoEmoji: {
    fontSize: 56,
    marginBottom: 8,
  },
  logoTitle: {
    fontSize: 22,
    fontWeight: '800',
    color: '#FFFFFF',
    letterSpacing: 0.5,
  },
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    padding: 24,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 4},
    shadowOpacity: 0.1,
    shadowRadius: 8,
    elevation: 5,
  },
  title: {
    fontSize: 22,
    fontWeight: '800',
    color: '#1A1A1A',
    marginBottom: 20,
    textAlign: 'center',
  },
  label: {
    fontSize: 13,
    fontWeight: '600',
    color: '#555',
    marginBottom: 4,
  },
  input: {
    borderWidth: 1,
    borderColor: '#E0E0E0',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 15,
    marginBottom: 14,
    backgroundColor: '#FAFAFA',
    color: '#1A1A1A',
  },
  button: {
    backgroundColor: '#2E7D32',
    borderRadius: 10,
    padding: 15,
    alignItems: 'center',
    marginTop: 6,
  },
  buttonDisabled: {
    backgroundColor: '#A5D6A7',
  },
  buttonText: {
    color: '#FFFFFF',
    fontWeight: '700',
    fontSize: 16,
  },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginTop: 16,
  },
  switchText: {
    color: '#666',
    fontSize: 14,
  },
  switchLink: {
    color: '#2E7D32',
    fontWeight: '700',
    fontSize: 14,
  },
});

export default RegisterScreen;
