import React, {useState, useCallback} from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  ScrollView, Alert,
} from 'react-native';
import {apiClient} from '../api/client';
import {useAuth} from '../context/AuthContext';
import {useLang} from '../context/LangContext';
import TurnstileWidget from '../components/TurnstileWidget';

const PRIMARY = '#2E7D32';

// Defined outside to keep a stable component reference between renders.
// If defined inside the component, React creates a new type on every
// keystroke and unmounts/remounts the TextInput, dismissing the keyboard.
interface FieldProps {
  label: string;
  value: string;
  onChangeText: (v: string) => void;
  placeholder?: string;
  keyboardType?: any;
  required?: boolean;
}
const Field: React.FC<FieldProps> = ({label, value, onChangeText, placeholder = '', keyboardType = 'default', required = false}) => (
  <>
    <Text style={styles.label}>{label}{required ? ' *' : ''}</Text>
    <TextInput
      style={styles.input}
      placeholder={placeholder}
      placeholderTextColor="#999"
      keyboardType={keyboardType}
      value={value}
      onChangeText={onChangeText}
    />
  </>
);

export default function SubmitViaScreen() {
  const {t} = useLang();
  const {user} = useAuth();
  const [form, setForm] = useState({
    name: '',
    location: '',
    latitude: '',
    longitude: '',
    difficulty: '',
    duration_hours: '',
    approach_time: '',
    return_time: '',
    elevation_gain: '',
    description: '',
    author_email: '',
  });
  const [saving, setSaving] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [turnstileToken, setTurnstileToken] = useState<string | null>(null);

  const setField = useCallback((key: string) => (v: string) => setForm(f => ({...f, [key]: v})), []);

  const handleSubmit = async () => {
    if (!form.name.trim() || !form.location.trim()) {
      Alert.alert(t('common.error'), t('submit.requiredFields'));
      return;
    }
    setSaving(true);
    try {
      await apiClient.post('/submit', {
        name: form.name.trim(),
        location: form.location.trim(),
        latitude: form.latitude ? parseFloat(form.latitude) : undefined,
        longitude: form.longitude ? parseFloat(form.longitude) : undefined,
        difficulty: form.difficulty ? parseInt(form.difficulty, 10) : undefined,
        duration_hours: form.duration_hours ? parseFloat(form.duration_hours) : undefined,
        approach_time: form.approach_time ? parseInt(form.approach_time, 10) : undefined,
        return_time: form.return_time ? parseInt(form.return_time, 10) : undefined,
        elevation_gain: form.elevation_gain ? parseInt(form.elevation_gain, 10) : undefined,
        description: form.description.trim() || undefined,
        author_email: form.author_email.trim() || undefined,
        turnstile_token: turnstileToken || undefined,
      });
      setSubmitted(true);
    } catch (err: any) {
      const serverMsg = err?.response?.data?.msg || err?.message || t('submit.error');
      Alert.alert(t('common.error'), serverMsg);
    } finally {
      setSaving(false);
    }
  };

  if (submitted) {
    return (
      <View style={styles.successContainer}>
        <Text style={styles.successIcon}>✅</Text>
        <Text style={styles.successTitle}>{t('submit.successTitle')}</Text>
        <Text style={styles.successMsg}>{t('submit.successMsg')}</Text>
        <TouchableOpacity
          style={styles.resetBtn}
          onPress={() => {
            setSubmitted(false);
            setForm({name:'',location:'',latitude:'',longitude:'',difficulty:'',duration_hours:'',approach_time:'',return_time:'',elevation_gain:'',description:'',author_email:''});
          }}>
          <Text style={styles.resetBtnText}>{t('submit.another')}</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.title}>{t('submit.title')}</Text>
      <Text style={styles.subtitle}>{t('submit.subtitle')}</Text>

      <Field label={t('submit.name')} value={form.name} onChangeText={setField('name')} placeholder="Via Ferrata de..." required />
      <Field label={t('submit.location')} value={form.location} onChangeText={setField('location')} placeholder="Commune, département" required />

      <View style={styles.row}>
        <View style={styles.half}>
          <Field label="Latitude GPS" value={form.latitude} onChangeText={setField('latitude')} placeholder="45.1234" keyboardType="decimal-pad" />
        </View>
        <View style={styles.half}>
          <Field label="Longitude GPS" value={form.longitude} onChangeText={setField('longitude')} placeholder="6.5678" keyboardType="decimal-pad" />
        </View>
      </View>

      <Field label={t('submit.difficulty')} value={form.difficulty} onChangeText={setField('difficulty')} placeholder="1-7" keyboardType="numeric" />
      <Field label={t('submit.duration')} value={form.duration_hours} onChangeText={setField('duration_hours')} placeholder="Ex: 2.5" keyboardType="decimal-pad" />

      <View style={styles.row}>
        <View style={styles.half}>
          <Field label={t('submit.approachTime')} value={form.approach_time} onChangeText={setField('approach_time')} placeholder="min" keyboardType="numeric" />
        </View>
        <View style={styles.half}>
          <Field label={t('submit.returnTime')} value={form.return_time} onChangeText={setField('return_time')} placeholder="min" keyboardType="numeric" />
        </View>
      </View>

      <Field label={t('submit.elevation')} value={form.elevation_gain} onChangeText={setField('elevation_gain')} placeholder="m" keyboardType="numeric" />

      <Text style={styles.label}>{t('submit.description')}</Text>
      <TextInput
        style={[styles.input, styles.textarea]}
        multiline
        numberOfLines={5}
        placeholder={t('submit.descriptionHint')}
        placeholderTextColor="#999"
        value={form.description}
        onChangeText={setField('description')}
        textAlignVertical="top"
      />

      <Field label={t('submit.email')} value={form.author_email} onChangeText={setField('author_email')} placeholder="votre@email.com" keyboardType="email-address" />

      {!user && <TurnstileWidget onVerify={token => setTurnstileToken(token)} />}

      <TouchableOpacity
        style={[styles.submitBtn, (saving || (!user && !turnstileToken)) && styles.disabled]}
        onPress={handleSubmit}
        disabled={saving || (!user && !turnstileToken)}>
        <Text style={styles.submitBtnText}>{saving ? '...' : t('submit.submit')}</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#f5f5f5'},
  content: {padding: 20},
  title: {fontSize: 24, fontWeight: 'bold', color: '#333', marginBottom: 4},
  subtitle: {color: '#666', fontSize: 14, marginBottom: 20},
  label: {fontSize: 13, color: '#555', marginBottom: 4, marginTop: 12, fontWeight: '500'},
  input: {borderWidth: 1, borderColor: '#ddd', borderRadius: 10, padding: 12, fontSize: 15, backgroundColor: '#fff', color: '#1A1A1A'},
  textarea: {height: 100, textAlignVertical: 'top'},
  row: {flexDirection: 'row', gap: 12},
  half: {flex: 1},
  submitBtn: {backgroundColor: PRIMARY, borderRadius: 10, padding: 16, alignItems: 'center', marginTop: 28, marginBottom: 40},
  submitBtnText: {color: '#fff', fontSize: 17, fontWeight: 'bold'},
  disabled: {opacity: 0.6},
  successContainer: {flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32, backgroundColor: '#f5f5f5'},
  successIcon: {fontSize: 60, marginBottom: 16},
  successTitle: {fontSize: 22, fontWeight: 'bold', color: '#333', marginBottom: 8, textAlign: 'center'},
  successMsg: {color: '#666', fontSize: 15, textAlign: 'center', marginBottom: 32},
  resetBtn: {backgroundColor: PRIMARY, borderRadius: 10, paddingHorizontal: 24, paddingVertical: 12},
  resetBtnText: {color: '#fff', fontWeight: 'bold', fontSize: 15},
});
