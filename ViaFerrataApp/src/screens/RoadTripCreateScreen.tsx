import React, {useState} from 'react';
import {
  View, Text, TextInput, TouchableOpacity, StyleSheet,
  ScrollView, Alert,
} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {createTrip} from '../api/client';
import {useLang} from '../context/LangContext';
import {RootStackParamList} from '../navigation/AppNavigator';

const PRIMARY = '#2E7D32';
type Nav = NativeStackNavigationProp<RootStackParamList>;

export default function RoadTripCreateScreen() {
  const {t} = useLang();
  const navigation = useNavigation<Nav>();
  const [form, setForm] = useState({
    name: '',
    description: '',
    start_date: '',
    end_date: '',
    nb_days: '3',
  });
  const [saving, setSaving] = useState(false);

  const handleCreate = async () => {
    if (!form.name.trim()) {
      Alert.alert(t('common.error'), t('trips.nameRequired'));
      return;
    }
    setSaving(true);
    try {
      const res = await createTrip({
        name: form.name.trim(),
        description: form.description.trim() || undefined,
        start_date: form.start_date.trim() || undefined,
        end_date: form.end_date.trim() || undefined,
        nb_days: parseInt(form.nb_days, 10) || 3,
      });
      const id = (res.data as any)?.data?.id ?? (res.data as any)?.id;
      if (id) {
        navigation.replace('RoadTripDetail', {id});
      } else {
        navigation.goBack();
      }
    } catch {
      Alert.alert(t('common.error'), t('trips.createError'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>{t('trips.newTrip')}</Text>

      <Text style={styles.label}>{t('trips.name')} *</Text>
      <TextInput
        style={styles.input}
        placeholder="Mon road trip Via Ferrata"
        placeholderTextColor="#999"
        value={form.name}
        onChangeText={v => setForm(f => ({...f, name: v}))}
      />

      <Text style={styles.label}>{t('trips.description')}</Text>
      <TextInput
        style={[styles.input, styles.textarea]}
        placeholder="Description de votre voyage..."
        placeholderTextColor="#999"
        multiline
        numberOfLines={3}
        value={form.description}
        onChangeText={v => setForm(f => ({...f, description: v}))}
      />

      <Text style={styles.label}>{t('trips.startDate')} (AAAA-MM-JJ)</Text>
      <TextInput
        style={styles.input}
        placeholder="2024-07-01"
        placeholderTextColor="#999"
        value={form.start_date}
        onChangeText={v => setForm(f => ({...f, start_date: v}))}
      />

      <Text style={styles.label}>{t('trips.endDate')} (AAAA-MM-JJ)</Text>
      <TextInput
        style={styles.input}
        placeholder="2024-07-07"
        placeholderTextColor="#999"
        value={form.end_date}
        onChangeText={v => setForm(f => ({...f, end_date: v}))}
      />

      <Text style={styles.label}>{t('trips.nbDays')}</Text>
      <TextInput
        style={styles.input}
        keyboardType="numeric"
        placeholder="3"
        placeholderTextColor="#999"
        value={form.nb_days}
        onChangeText={v => setForm(f => ({...f, nb_days: v}))}
      />

      <TouchableOpacity
        style={[styles.createBtn, saving && styles.disabled]}
        onPress={handleCreate}
        disabled={saving}>
        <Text style={styles.createBtnText}>
          {saving ? '...' : t('trips.create')}
        </Text>
      </TouchableOpacity>

      <TouchableOpacity style={styles.cancelLink} onPress={() => navigation.goBack()}>
        <Text style={styles.cancelLinkText}>{t('common.cancel')}</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#f5f5f5'},
  content: {padding: 20},
  title: {fontSize: 24, fontWeight: 'bold', color: '#333', marginBottom: 20},
  label: {fontSize: 13, color: '#555', marginBottom: 4, marginTop: 12, fontWeight: '500'},
  input: {borderWidth: 1, borderColor: '#ddd', borderRadius: 10, padding: 12, fontSize: 15, backgroundColor: '#fff', color: '#1A1A1A'},
  textarea: {height: 80, textAlignVertical: 'top'},
  createBtn: {backgroundColor: PRIMARY, borderRadius: 10, padding: 16, alignItems: 'center', marginTop: 28},
  createBtnText: {color: '#fff', fontSize: 17, fontWeight: 'bold'},
  disabled: {opacity: 0.6},
  cancelLink: {alignItems: 'center', marginTop: 16, padding: 8},
  cancelLinkText: {color: '#888', fontSize: 14},
});
