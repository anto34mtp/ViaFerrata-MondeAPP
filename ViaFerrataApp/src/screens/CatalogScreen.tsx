import React, {useState, useEffect, useCallback, useRef} from 'react';
import {
  View,
  Text,
  TextInput,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  Modal,
  ScrollView,
  ActivityIndicator,
} from 'react-native';
import {useNavigation, useRoute} from '@react-navigation/native';
import {getVias, Via} from '../api/client';
import {useLang} from '../context/LangContext';
import ViaCard from '../components/ViaCard';

const DIFFICULTIES = [1, 2, 3, 4, 5, 6, 7];
const DIFF_LABELS: Record<number, string> = {
  1: 'F', 2: 'PD', 3: 'AD', 4: 'D', 5: 'TD', 6: 'ED', 7: 'EX',
};

const CatalogScreen: React.FC = () => {
  const {t} = useLang();
  const navigation = useNavigation<any>();
  const route = useRoute<any>();

  const [vias, setVias] = useState<Via[]>([]);
  const [loading, setLoading] = useState(false);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [hasMore, setHasMore] = useState(true);

  // Filters
  const [search, setSearch] = useState('');
  const [country, setCountry] = useState('');
  const [departmentCode, setDepartmentCode] = useState('');
  const [diffMin, setDiffMin] = useState('');
  const [diffMax, setDiffMax] = useState('');
  const [orderBy, setOrderBy] = useState(route.params?.order_by || '');
  const [showFilters, setShowFilters] = useState(false);

  // Temp filter state for modal
  const [tmpCountry, setTmpCountry] = useState('');
  const [tmpDept, setTmpDept] = useState('');
  const [tmpDiffMin, setTmpDiffMin] = useState('');
  const [tmpDiffMax, setTmpDiffMax] = useState('');
  const [tmpOrderBy, setTmpOrderBy] = useState('');

  const searchTimer = useRef<ReturnType<typeof setTimeout>>();

  const fetchVias = useCallback(
    async (p: number, reset = false) => {
      setLoading(true);
      try {
        const res = await getVias({
          page: p,
          limit: 20,
          search,
          country,
          department_code: departmentCode,
          difficulty_min: diffMin,
          difficulty_max: diffMax,
          order_by: orderBy,
        });
        const body = res.data as any;
        const data: Via[] = Array.isArray(body) ? body : body?.items || [];
        const tot: number = body?.total ?? data.length;
        if (reset) {
          setVias(data);
        } else {
          setVias(prev => [...prev, ...data]);
        }
        setTotal(tot);
        setHasMore(data.length === 20);
      } catch {
        // silent
      } finally {
        setLoading(false);
      }
    },
    [search, country, departmentCode, diffMin, diffMax, orderBy],
  );

  useEffect(() => {
    setPage(1);
    fetchVias(1, true);
  }, [fetchVias]);

  const handleSearchChange = (text: string) => {
    setSearch(text);
    clearTimeout(searchTimer.current);
    searchTimer.current = setTimeout(() => {
      setPage(1);
    }, 500);
  };

  const loadMore = () => {
    if (!loading && hasMore) {
      const nextPage = page + 1;
      setPage(nextPage);
      fetchVias(nextPage, false);
    }
  };

  const openFilters = () => {
    setTmpCountry(country);
    setTmpDept(departmentCode);
    setTmpDiffMin(diffMin);
    setTmpDiffMax(diffMax);
    setTmpOrderBy(orderBy);
    setShowFilters(true);
  };

  const applyFilters = () => {
    setCountry(tmpCountry);
    setDepartmentCode(tmpDept);
    setDiffMin(tmpDiffMin);
    setDiffMax(tmpDiffMax);
    setOrderBy(tmpOrderBy);
    setShowFilters(false);
    setPage(1);
  };

  const resetFilters = () => {
    setTmpCountry('');
    setTmpDept('');
    setTmpDiffMin('');
    setTmpDiffMax('');
    setTmpOrderBy('');
  };

  const renderItem = ({item}: {item: Via}) => (
    <ViaCard
      via={item}
      onPress={() =>
        navigation.navigate('ViaDetail', {slug: item.slug, name: item.name})
      }
    />
  );

  const renderFooter = () => {
    if (!loading) return null;
    return <ActivityIndicator style={{margin: 16}} color="#2E7D32" />;
  };

  return (
    <View style={styles.container}>
      {/* Search bar */}
      <View style={styles.searchRow}>
        <TextInput
          style={styles.searchInput}
          placeholder={t.catalog.searchPlaceholder}
          value={search}
          onChangeText={handleSearchChange}
          returnKeyType="search"
          clearButtonMode="while-editing"
        />
        <TouchableOpacity style={styles.filterBtn} onPress={openFilters}>
          <Text style={styles.filterBtnText}>⚙️</Text>
        </TouchableOpacity>
      </View>

      {/* Results count */}
      <Text style={styles.resultCount}>
        {total} {t.catalog.results}
      </Text>

      <FlatList
        data={vias}
        keyExtractor={item => String(item.id)}
        renderItem={renderItem}
        onEndReached={loadMore}
        onEndReachedThreshold={0.3}
        ListFooterComponent={renderFooter}
        ListEmptyComponent={
          !loading ? (
            <Text style={styles.empty}>{t.catalog.noVias}</Text>
          ) : null
        }
        contentContainerStyle={{paddingBottom: 16}}
      />

      {/* Filter Modal */}
      <Modal
        visible={showFilters}
        animationType="slide"
        presentationStyle="pageSheet"
        onRequestClose={() => setShowFilters(false)}>
        <View style={styles.modal}>
          <View style={styles.modalHeader}>
            <Text style={styles.modalTitle}>{t.catalog.filters}</Text>
            <TouchableOpacity onPress={() => setShowFilters(false)}>
              <Text style={styles.modalClose}>{t.common.close}</Text>
            </TouchableOpacity>
          </View>
          <ScrollView style={styles.modalContent}>
            <Text style={styles.filterLabel}>{t.catalog.country}</Text>
            <TextInput
              style={styles.filterInput}
              value={tmpCountry}
              onChangeText={setTmpCountry}
              placeholder="France, Espagne..."
            />

            <Text style={styles.filterLabel}>{t.catalog.department}</Text>
            <TextInput
              style={styles.filterInput}
              value={tmpDept}
              onChangeText={setTmpDept}
              placeholder="74, 06..."
            />

            <Text style={styles.filterLabel}>{t.catalog.difficultyMin}</Text>
            <View style={styles.diffRow}>
              {DIFFICULTIES.map(d => (
                <TouchableOpacity
                  key={d}
                  onPress={() =>
                    setTmpDiffMin(tmpDiffMin === String(d) ? '' : String(d))
                  }
                  style={[
                    styles.diffChip,
                    tmpDiffMin === String(d) && styles.diffChipActive,
                  ]}>
                  <Text
                    style={[
                      styles.diffChipText,
                      tmpDiffMin === String(d) && styles.diffChipTextActive,
                    ]}>
                    {DIFF_LABELS[d]}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>

            <Text style={styles.filterLabel}>{t.catalog.difficultyMax}</Text>
            <View style={styles.diffRow}>
              {DIFFICULTIES.map(d => (
                <TouchableOpacity
                  key={d}
                  onPress={() =>
                    setTmpDiffMax(tmpDiffMax === String(d) ? '' : String(d))
                  }
                  style={[
                    styles.diffChip,
                    tmpDiffMax === String(d) && styles.diffChipActive,
                  ]}>
                  <Text
                    style={[
                      styles.diffChipText,
                      tmpDiffMax === String(d) && styles.diffChipTextActive,
                    ]}>
                    {DIFF_LABELS[d]}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>

            <Text style={styles.filterLabel}>{t.catalog.sortBy}</Text>
            {[
              {val: '', label: t.catalog.sortName},
              {val: 'difficulty', label: t.catalog.sortDifficulty},
              {val: 'rating', label: t.catalog.sortRating},
              {val: 'recent', label: t.catalog.sortRecent},
            ].map(opt => (
              <TouchableOpacity
                key={opt.val}
                onPress={() => setTmpOrderBy(opt.val)}
                style={styles.sortOption}>
                <View
                  style={[
                    styles.radio,
                    tmpOrderBy === opt.val && styles.radioActive,
                  ]}
                />
                <Text style={styles.sortOptionText}>{opt.label}</Text>
              </TouchableOpacity>
            ))}
          </ScrollView>

          <View style={styles.modalFooter}>
            <TouchableOpacity style={styles.resetBtn} onPress={resetFilters}>
              <Text style={styles.resetBtnText}>{t.catalog.resetFilters}</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.applyBtn} onPress={applyFilters}>
              <Text style={styles.applyBtnText}>{t.catalog.applyFilters}</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#F5F5F5'},
  searchRow: {
    flexDirection: 'row',
    margin: 12,
    alignItems: 'center',
  },
  searchInput: {
    flex: 1,
    backgroundColor: '#FFFFFF',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 10,
    fontSize: 15,
    borderWidth: 1,
    borderColor: '#E0E0E0',
    color: '#1A1A1A',
  },
  filterBtn: {
    marginLeft: 10,
    backgroundColor: '#2E7D32',
    borderRadius: 10,
    padding: 10,
  },
  filterBtnText: {
    fontSize: 18,
  },
  resultCount: {
    paddingHorizontal: 16,
    marginBottom: 4,
    fontSize: 13,
    color: '#666',
  },
  empty: {
    textAlign: 'center',
    padding: 32,
    color: '#999',
    fontSize: 15,
  },
  modal: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#E0E0E0',
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: '700',
  },
  modalClose: {
    color: '#2E7D32',
    fontSize: 16,
  },
  modalContent: {
    flex: 1,
    padding: 16,
  },
  filterLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333',
    marginTop: 16,
    marginBottom: 6,
  },
  filterInput: {
    borderWidth: 1,
    borderColor: '#E0E0E0',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    fontSize: 15,
    color: '#1A1A1A',
    backgroundColor: '#FFFFFF',
  },
  diffRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  diffChip: {
    borderWidth: 1,
    borderColor: '#2E7D32',
    borderRadius: 6,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  diffChipActive: {
    backgroundColor: '#2E7D32',
  },
  diffChipText: {
    color: '#2E7D32',
    fontWeight: '700',
  },
  diffChipTextActive: {
    color: '#FFFFFF',
  },
  sortOption: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
  },
  radio: {
    width: 18,
    height: 18,
    borderRadius: 9,
    borderWidth: 2,
    borderColor: '#2E7D32',
    marginRight: 10,
  },
  radioActive: {
    backgroundColor: '#2E7D32',
  },
  sortOptionText: {
    fontSize: 15,
    color: '#333',
  },
  modalFooter: {
    flexDirection: 'row',
    padding: 16,
    gap: 12,
    borderTopWidth: 1,
    borderTopColor: '#E0E0E0',
  },
  resetBtn: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#2E7D32',
    borderRadius: 10,
    padding: 14,
    alignItems: 'center',
  },
  resetBtnText: {
    color: '#2E7D32',
    fontWeight: '600',
  },
  applyBtn: {
    flex: 1,
    backgroundColor: '#2E7D32',
    borderRadius: 10,
    padding: 14,
    alignItems: 'center',
  },
  applyBtnText: {
    color: '#FFFFFF',
    fontWeight: '600',
  },
});

export default CatalogScreen;
